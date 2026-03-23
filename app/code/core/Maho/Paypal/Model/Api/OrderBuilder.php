<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Api_OrderBuilder
{
    /**
     * Build a PayPal OrderRequest from a Mage quote
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
            $hasShippingAddress = $shippingAddress && $shippingAddress->getFirstname();

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
            'invoice_id' => $quote->setReservedOrderId('')->reserveOrderId()->getReservedOrderId(),
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
        if ($shippingAddress && $shippingAddress->getFirstname()) {
            $purchaseUnit['shipping'] = $this->_buildShipping($shippingAddress);
        }

        return $purchaseUnit;
    }

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
     * @return array<array{name: string, quantity: string, unit_amount: array{currency_code: string, value: string}}>
     */
    protected function _buildLineItems(Mage_Sales_Model_Quote $quote, string $currency): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $qty = (int) $quoteItem->getTotalQty();
            $price = (float) $quoteItem->getBaseCalculationPrice();

            // Handle precision issues (same logic as Mage_Paypal_Model_Cart::_addRegularItem)
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
