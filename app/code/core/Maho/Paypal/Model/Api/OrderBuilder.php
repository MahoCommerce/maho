<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * @phpstan-type Money array{currency_code: string, value: string}
 * @phpstan-type Breakdown array{item_total: Money, tax_total: Money, shipping?: Money, discount?: Money}
 * @phpstan-type LineItem array{name: string, quantity: string, sku: string, unit_amount: Money, category: 'DIGITAL_GOODS'|'PHYSICAL_GOODS'}
 * @phpstan-type Shipping array{name: array{full_name: string}, address?: array{address_line_1: string, admin_area_2: string, admin_area_1: string, postal_code: string, country_code: string, address_line_2?: string}}
 * @phpstan-type PurchaseUnit array{reference_id: string, amount: array{currency_code: string, value: string, breakdown?: Breakdown}, invoice_id: string|null, items?: list<LineItem>, shipping?: Shipping}
 * @phpstan-type ExperienceContext array{user_action: string, return_url?: string, cancel_url?: string, shipping_preference?: string, order_update_callback_config?: array{callback_events: list<string>, callback_url: string}}
 * @phpstan-type VaultAttributes array{vault?: array{store_in_vault: string, usage_type: string, customer_type: string}, customer?: array{id: string}}
 * @phpstan-type PaymentSource array{paypal?: array{experience_context?: ExperienceContext, vault_id?: string, attributes?: VaultAttributes}, card?: array{vault_id?: string, attributes?: VaultAttributes}}
 * @phpstan-type OrderRequest array{intent: string, purchase_units: list<PurchaseUnit>, payment_source?: PaymentSource}
 */
class Maho_Paypal_Model_Api_OrderBuilder
{
    /**
     * Build a PayPal OrderRequest from a Mage quote
     *
     * @return OrderRequest
     */
    public function buildFromQuote(
        Mage_Sales_Model_Quote $quote,
        string $intent = 'AUTHORIZE',
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
        ?string $vaultPaymentSource = null,
        ?string $vaultPaypalTokenId = null,
        ?string $vaultSourceType = null,
        ?string $shippingCallbackUrl = null,
    ): array {
        $currency = $quote->getBaseCurrencyCode();
        $grandTotal = $this->_formatAmount((float) $quote->getBaseGrandTotal());

        $orderRequest = [
            'intent' => strtoupper($intent),
            'purchase_units' => [
                $this->_buildPurchaseUnit($quote, $currency, $grandTotal),
            ],
        ];

        if ($returnUrl && $cancelUrl) {
            $shippingAddress = $quote->getShippingAddress();
            $hasShippingAddress = (bool) $shippingAddress->getFirstname();

            if ($quote->isVirtual()) {
                $shippingPreference = 'NO_SHIPPING';
            } elseif ($hasShippingAddress) {
                $shippingPreference = 'SET_PROVIDED_ADDRESS';
            } else {
                $shippingPreference = 'GET_FROM_FILE';
            }

            $experienceContext = [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
                'shipping_preference' => $shippingPreference,
            ];

            if ($shippingPreference === 'GET_FROM_FILE' && $shippingCallbackUrl) {
                $experienceContext['order_update_callback_config'] = [
                    'callback_events' => ['SHIPPING_ADDRESS', 'SHIPPING_OPTIONS'],
                    'callback_url' => $shippingCallbackUrl,
                ];
            }

            $orderRequest['payment_source'] = [
                'paypal' => [
                    'experience_context' => $experienceContext,
                ],
            ];
        }

        if ($vaultPaypalTokenId && $vaultSourceType) {
            $this->_addVaultTokenPaymentSource($orderRequest, $vaultPaypalTokenId, $vaultSourceType);
        } elseif ($vaultPaymentSource && $quote->getCustomerId()) {
            $this->_addVaultAttributes($orderRequest, $vaultPaymentSource, (string) $quote->getCustomerId());
        }

        return $orderRequest;
    }

    /**
     * @param OrderRequest $orderRequest
     */
    protected function _addVaultAttributes(array &$orderRequest, string $paymentSource, string $customerId): void
    {
        $vaultData = [
            'store_in_vault' => 'ON_SUCCESS',
            'usage_type' => 'MERCHANT',
            'customer_type' => 'CONSUMER',
        ];

        if ($paymentSource === 'paypal') {
            $orderRequest['payment_source']['paypal']['attributes']['vault'] = $vaultData;
            $orderRequest['payment_source']['paypal']['attributes']['customer'] = [
                'id' => $customerId,
            ];
        } elseif ($paymentSource === 'card') {
            $orderRequest['payment_source']['card']['attributes']['vault'] = $vaultData;
            $orderRequest['payment_source']['card']['attributes']['customer'] = [
                'id' => $customerId,
            ];
        }
    }

    /**
     * @param OrderRequest $orderRequest
     */
    protected function _addVaultTokenPaymentSource(array &$orderRequest, string $paypalTokenId, string $sourceType): void
    {
        if ($sourceType === 'card') {
            $orderRequest['payment_source'] = [
                'card' => [
                    'vault_id' => $paypalTokenId,
                ],
            ];
        } elseif ($sourceType === 'paypal') {
            $orderRequest['payment_source'] = [
                'paypal' => [
                    'vault_id' => $paypalTokenId,
                    'experience_context' => [
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ];
        }
    }

    /**
     * @return PurchaseUnit
     */
    protected function _buildPurchaseUnit(
        Mage_Sales_Model_Quote $quote,
        string $currency,
        string $grandTotal,
    ): array {
        $purchaseUnit = [
            'reference_id' => 'default',
            'amount' => [
                'currency_code' => $currency,
                'value' => $grandTotal,
            ],
            'invoice_id' => $quote->getReservedOrderId() ?: $quote->reserveOrderId()->getReservedOrderId(),
        ];

        $breakdown = $this->buildBreakdown($quote, $currency);
        if ($breakdown !== null) {
            $purchaseUnit['amount']['breakdown'] = $breakdown;
            $items = $this->_buildLineItems($quote, $currency);
            if ($items !== []) {
                $purchaseUnit['items'] = $items;
            }
        }

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress->getFirstname()) {
            $purchaseUnit['shipping'] = $this->_buildShipping($shippingAddress);
        }

        return $purchaseUnit;
    }

    /**
     * @return Breakdown|null
     */
    public function buildBreakdown(Mage_Sales_Model_Quote $quote, string $currency): ?array
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $subtotal = 0.0;
        $items = $quote->getAllVisibleItems();
        foreach ($items as $item) {
            $qty = (int) $item->getTotalQty();
            $price = (float) $item->getBaseCalculationPrice();
            if ($this->_hasPrecisionIssue($price)
                || round($price * $qty, 2) !== round((float) $item->getBaseRowTotal(), 2)
            ) {
                $subtotal += round((float) $item->getBaseRowTotal(), 2);
            } else {
                $subtotal += round($price, 2) * $qty;
            }
        }

        $tax = (float) $address->getBaseTaxAmount();
        $shipping = (float) $address->getBaseShippingAmount();
        $discount = abs((float) $address->getBaseDiscountAmount());

        $calculated = round($subtotal + $tax + $shipping - $discount, 2);
        $grandTotal = round((float) $quote->getBaseGrandTotal(), 2);

        if (sprintf('%.2F', $calculated) !== sprintf('%.2F', $grandTotal)) {
            // Rounding mismatch — omit line items and send only total
            return null;
        }

        $breakdown = [
            'item_total' => ['currency_code' => $currency, 'value' => $this->_formatAmount($subtotal)],
            'tax_total' => ['currency_code' => $currency, 'value' => $this->_formatAmount($tax)],
        ];

        if ($shipping > 0) {
            $breakdown['shipping'] = ['currency_code' => $currency, 'value' => $this->_formatAmount($shipping)];
        }

        if ($discount > 0) {
            $breakdown['discount'] = ['currency_code' => $currency, 'value' => $this->_formatAmount($discount)];
        }

        return $breakdown;
    }

    /**
     * @return list<LineItem>
     */
    protected function _buildLineItems(Mage_Sales_Model_Quote $quote, string $currency): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $qty = (int) $quoteItem->getTotalQty();
            $price = (float) $quoteItem->getBaseCalculationPrice();

            // Float prices like 12.345 round to a different total when multiplied by qty
            // (12.345 × 3 = 37.035) than when summed (37.04). Fall back to row total in
            // those cases so PayPal's line-item check matches the cart total to the cent.
            if ($this->_hasPrecisionIssue($price)) {
                $price = (float) $quoteItem->getBaseRowTotal();
                $qty = 1;
            } elseif (round($price * $qty, 2) !== round((float) $quoteItem->getBaseRowTotal(), 2)) {
                $price = (float) $quoteItem->getBaseRowTotal();
                $qty = 1;
            }

            $name = mb_substr((string) $quoteItem->getName(), 0, 127);

            $items[] = [
                'name' => $name,
                'quantity' => (string) $qty,
                'sku' => mb_substr((string) $quoteItem->getSku(), 0, 127),
                'unit_amount' => [
                    'currency_code' => $currency,
                    'value' => $this->_formatAmount($price),
                ],
                'category' => $quoteItem->getIsVirtual() ? 'DIGITAL_GOODS' : 'PHYSICAL_GOODS',
            ];
        }
        return $items;
    }

    /**
     * @return Shipping
     */
    protected function _buildShipping(Mage_Sales_Model_Quote_Address $address): array
    {
        $shipping = [
            'name' => [
                'full_name' => trim($address->getFirstname() . ' ' . $address->getLastname()),
            ],
        ];

        $street = $address->getStreet();
        if (!empty($street[0])) {
            $shipping['address'] = [
                'address_line_1' => mb_substr($street[0], 0, 300),
                'admin_area_2' => mb_substr((string) $address->getCity(), 0, 120),
                'admin_area_1' => mb_substr((string) $address->getRegionCode(), 0, 300),
                'postal_code' => mb_substr((string) $address->getPostcode(), 0, 60),
                'country_code' => (string) $address->getCountryId(),
            ];
            if (!empty($street[1])) {
                $shipping['address']['address_line_2'] = mb_substr($street[1], 0, 300);
            }
        }

        return $shipping;
    }

    protected function _hasPrecisionIssue(float $amount): bool
    {
        return $amount - round($amount, 2) != 0;
    }

    protected function _formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
