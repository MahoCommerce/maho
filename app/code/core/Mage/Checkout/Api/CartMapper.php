<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use Mage\Customer\Api\Address;

/**
 * Shared cart-to-DTO mapping logic used by both CartProvider and CartProcessor.
 */
class CartMapper
{
    /**
     * Map Maho quote model to Cart DTO.
     *
     * Built in the quote's store scope: media URLs, store-scoped attribute
     * values and shipping rates must present the cart in its own store's
     * terms, whatever scope the API caller requested.
     *
     * The mapper owns read-boundary totals: a quote whose totals were not yet
     * collected in this request is collected here, so callers never thread a
     * collect-on-load flag. Pass $collectTotals: false only when fresh totals
     * cannot matter: a just-created empty cart, or a DTO built to be discarded
     * (CartProvider on write operations).
     */
    public function mapQuoteToCart(\Mage_Sales_Model_Quote $quote, bool $collectTotals = true): Cart
    {
        return CartService::inQuoteStoreScope($quote, fn(): Cart => $this->buildCartDto($quote, $collectTotals));
    }

    private function buildCartDto(\Mage_Sales_Model_Quote $quote, bool $collectTotals): Cart
    {
        if ($collectTotals && !$quote->getTotalsCollectedFlag()) {
            CartService::collectAndVerifyTotals($quote);
        }

        $cart = new Cart();
        $cart->id = (int) $quote->getId();
        // The masked id is the guest bearer credential; once a customer owns the
        // cart it grants nothing (see verifyCartAccess), so don't echo it.
        $cart->maskedId = $quote->getCustomerId() ? null : $quote->getData('masked_quote_id');
        $cart->customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $cart->customerEmail = $quote->getCustomerEmail();
        $cart->customerNote = $quote->getCustomerNote();
        $cart->reservedOrderId = $quote->getReservedOrderId();
        $cart->storeId = (int) $quote->getStoreId();
        $cart->isActive = (bool) $quote->getIsActive();
        $cart->currency = $quote->getStore()->getCurrentCurrencyCode();
        $cart->itemsCount = (int) $quote->getItemsCount();
        $cart->itemsQty = (float) $quote->getItemsQty();
        $cart->createdAt = $quote->getCreatedAt();
        $cart->updatedAt = $quote->getUpdatedAt();
        $cart->giftMessage = $this->mapGiftMessage($quote);

        // Batch load product thumbnails and stock status to avoid N+1 queries
        $items = $quote->getAllVisibleItems();
        $thumbnailsByProductId = $this->batchLoadCartItemThumbnails($items);
        $stockStatusByProductId = $this->batchLoadStockStatus($items);

        $cart->items = [];
        foreach ($items as $item) {
            $productId = $item->getProductId() ? (int) $item->getProductId() : null;
            $thumbnailUrl = $productId ? ($thumbnailsByProductId[$productId] ?? null) : null;
            $stockStatus = $productId ? ($stockStatusByProductId[$productId] ?? null) : null;
            $cart->items[] = $this->mapItemToDto($item, $thumbnailUrl, $stockStatus);
        }

        // Map prices
        $cart->prices = $this->mapPricesToArray($quote);

        // Map billing address
        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress && $billingAddress->getId()) {
            $cart->billingAddress = Address::fromQuoteAddress($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getId()) {
            $cart->shippingAddress = Address::fromQuoteAddress($shippingAddress);

            // Get available shipping methods
            $cart->availableShippingMethods = $this->getAvailableShippingMethods($shippingAddress);

            // Get selected shipping method
            if ($shippingAddress->getShippingMethod()) {
                $cart->selectedShippingMethod = $this->parseShippingMethod($shippingAddress);
            }
        }

        // Get available payment methods
        $cart->availablePaymentMethods = $this->getAvailablePaymentMethods($quote);

        // Get selected payment method
        $payment = $quote->getPayment();
        if ($payment && $payment->getMethod()) {
            try {
                $cart->selectedPaymentMethod = [
                    'code' => $payment->getMethod(),
                    'title' => $payment->getMethodInstance()->getTitle(),
                ];
            } catch (\Exception) {
                $cart->selectedPaymentMethod = [
                    'code' => $payment->getMethod(),
                    'title' => $payment->getMethod(),
                ];
            }
        }

        // Get applied coupon. Virtual/downloadable-only carts accumulate
        // totals on the billing address, so read discounts from there.
        $totalsAddress = $quote->isVirtual() ? $quote->getBillingAddress() : $shippingAddress;
        $couponCode = $quote->getCouponCode();
        if ($couponCode) {
            $cart->appliedCoupon = [
                'code' => $couponCode,
                'discountAmount' => (float) abs($totalsAddress ? $totalsAddress->getDiscountAmount() : 0),
            ];
        }

        // Get applied gift cards
        $giftcardCodesJson = $quote->getData('giftcard_codes');
        if ($giftcardCodesJson) {
            $giftcardCodes = \Mage::helper('core')->jsonDecode($giftcardCodesJson, true);
            if (is_array($giftcardCodes)) {
                // giftcard_codes stores {code: applied_amount} in base
                // currency; the collector prunes invalid cards on collect,
                // skip any lingering in a not-yet-collected quote. Convert
                // both fields like the collector converts the discount so the
                // element agrees with prices['giftcardAmount']. The GraphQL
                // cart handler maps through here too.
                $store = $quote->getStore();
                $websiteId = (int) $store->getWebsiteId();
                foreach ($giftcardCodes as $code => $appliedAmount) {
                    /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
                    $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode((string) $code);
                    if (!$giftcard->getId() || !$giftcard->isValidForWebsite($websiteId)) {
                        continue;
                    }
                    // isValidForWebsite() ensures the card belongs to this
                    // website, so its raw balance is already base currency;
                    // the bare call cannot throw on a missing rate row.
                    $balance = $giftcard->getBalance();
                    $cart->appliedGiftcards[] = [
                        'code' => (string) $code,
                        'balance' => (float) $store->roundPrice($store->convertPrice($balance, false)),
                        'appliedAmount' => (float) $store->roundPrice($store->convertPrice((float) $appliedAmount, false)),
                    ];
                }
            }
        }

        // Populate giftcard amount in prices from quote
        $giftcardAmount = (float) $quote->getData('giftcard_amount');
        if ($giftcardAmount > 0) {
            $cart->prices['giftcardAmount'] = $giftcardAmount;
        }

        \Mage::dispatchEvent('api_cart_dto_build', ['quote' => $quote, 'dto' => $cart]);
        return $cart;
    }

    /**
     * Map Maho quote item model to CartItem DTO
     */
    public function mapItemToDto(
        \Mage_Sales_Model_Quote_Item $item,
        ?string $preloadedThumbnailUrl = null,
        ?string $preloadedStockStatus = null,
    ): CartItem {
        $dto = new CartItem();
        $dto->id = (int) $item->getId();
        $dto->sku = $item->getSku();
        $dto->name = $item->getName() ?? '';
        $dto->qty = (float) $item->getQty();
        // Quote currency, like every other non-base money field in the response.
        // getPrice() is website base currency; getCalculationPrice() is the
        // converted (or custom) unit price the totals pipeline multiplies, and
        // calcRowTotal() rounds it first, so round here to keep price * qty
        // equal to rowTotal. Tax-inclusive Row/Total calculation derives the
        // unit price from an already-rounded row total, so there the two can
        // still differ by up to one cent.
        $dto->price = (float) $item->getStore()->roundPrice($item->getCalculationPrice());
        $dto->priceInclTax = (float) $item->getPriceInclTax();
        $dto->rowTotal = (float) $item->getRowTotal();
        $dto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
        $dto->rowTotalWithDiscount = max(0.0, (float) $item->getRowTotal() - (float) $item->getDiscountAmount());
        $dto->discountAmount = $item->getDiscountAmount() ? (float) $item->getDiscountAmount() : null;
        $dto->discountPercent = $item->getDiscountPercent() ? (float) $item->getDiscountPercent() : null;
        $dto->taxAmount = $item->getTaxAmount() ? (float) $item->getTaxAmount() : null;
        $dto->taxPercent = $item->getTaxPercent() ? (float) $item->getTaxPercent() : null;
        $dto->productId = $item->getProductId() ? (int) $item->getProductId() : null;
        $dto->productType = $item->getProductType();
        $dto->thumbnailUrl = $preloadedThumbnailUrl;
        // Products with no stock row (e.g. unmanaged items) aren't in the
        // batch-loaded map; fall back to the DTO's non-nullable default rather
        // than assigning null to the typed string property (TypeError).
        $dto->stockStatus = $preloadedStockStatus ?? 'in_stock';

        // Get configured product options for display
        $dto->options = $this->getItemConfigurationOptions($item);

        $dto->giftMessage = $this->mapGiftMessage($item);

        \Mage::dispatchEvent('api_cart_item_dto_build', ['item' => $item, 'dto' => $dto]);
        return $dto;
    }

    /**
     * Read the gift message attached to a quote or quote item into the API
     * shape, or null when none is set. Safe to call when the GiftMessage module
     * is absent (returns null).
     *
     * @return array{sender: string, recipient: string, message: string}|null
     */
    private function mapGiftMessage(\Maho\DataObject $entity): ?array
    {
        $messageId = (int) $entity->getGiftMessageId();
        if (!$messageId) {
            return null;
        }

        $message = \Mage::getModel('giftmessage/message')->load($messageId);
        if (!$message->getId()) {
            return null;
        }

        return [
            'sender' => (string) $message->getSender(),
            'recipient' => (string) $message->getRecipient(),
            'message' => (string) $message->getMessage(),
        ];
    }

    /**
     * Batch load thumbnails for all cart items to avoid N+1 product loads
     *
     * @param \Mage_Sales_Model_Quote_Item[] $items
     * @return array<int, string> Map of product ID => thumbnail URL
     */
    public function batchLoadCartItemThumbnails(array $items): array
    {
        $productIds = [];
        foreach ($items as $item) {
            if ($item->getProductId()) {
                $productIds[] = (int) $item->getProductId();
            }
        }

        if (empty($productIds)) {
            return [];
        }

        $collection = \Mage::getResourceModel('catalog/product_collection')
            ->addIdFilter($productIds)
            ->addAttributeToSelect(['small_image', 'thumbnail']);

        $thumbnails = [];
        $mediaConfig = \Mage::getModel('catalog/product_media_config');

        foreach ($collection as $product) {
            $image = $product->getSmallImage() ?: $product->getThumbnail();
            if ($image && $image !== 'no_selection') {
                $thumbnails[(int) $product->getId()] = $mediaConfig->getMediaUrl($image);
            }
        }

        return $thumbnails;
    }

    /**
     * Batch load stock status for all cart items to avoid N+1 queries
     *
     * @param \Mage_Sales_Model_Quote_Item[] $items
     * @return array<int, string> Map of product ID => 'in_stock'|'out_of_stock'
     */
    public function batchLoadStockStatus(array $items): array
    {
        $productIds = [];
        foreach ($items as $item) {
            if ($item->getProductId()) {
                $productIds[] = (int) $item->getProductId();
            }
        }

        if (empty($productIds)) {
            return [];
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $table = $resource->getTableName('cataloginventory/stock_item');

        $rows = $read->fetchAll(
            $read->select()
                ->from($table, ['product_id', 'is_in_stock'])
                ->where('product_id IN (?)', $productIds),
        );

        $statuses = [];
        foreach ($rows as $row) {
            $statuses[(int) $row['product_id']] = ((int) $row['is_in_stock']) ? 'in_stock' : 'out_of_stock';
        }

        return $statuses;
    }

    /**
     * Map Maho quote to prices array (shape documented by the CartPrices DTO)
     *
     * @return array<string, float|null>
     */
    public function mapPricesToArray(\Mage_Sales_Model_Quote $quote): array
    {
        // Virtual/downloadable-only carts accumulate discount and tax totals on
        // the billing address rather than the shipping address.
        $totalsAddress = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $shippingAddress = $quote->getShippingAddress();

        $prices = [
            'subtotal' => (float) $quote->getSubtotal(),
            'subtotalInclTax' => (float) array_reduce($quote->getAllVisibleItems(), fn(float $sum, $item) => $sum + (float) $item->getRowTotalInclTax(), 0.0),
            'subtotalWithDiscount' => (float) $quote->getSubtotalWithDiscount(),
            'discountAmount' => null,
            'shippingAmount' => null,
            'shippingAmountInclTax' => null,
            'taxAmount' => 0.0,
            'shippingTaxAmount' => null,
            'grandTotal' => (float) $quote->getGrandTotal(),
            'baseGrandTotal' => (float) $quote->getBaseGrandTotal(),
            'baseSubtotal' => (float) $quote->getBaseSubtotal(),
            'baseTaxAmount' => 0.0,
            'baseShippingAmount' => null,
            'baseDiscountAmount' => null,
            'giftcardAmount' => null,
        ];

        if ($totalsAddress) {
            $prices['discountAmount'] = $totalsAddress->getDiscountAmount()
                ? (float) abs($totalsAddress->getDiscountAmount())
                : null;
            $prices['baseDiscountAmount'] = $totalsAddress->getBaseDiscountAmount()
                ? (float) abs($totalsAddress->getBaseDiscountAmount())
                : null;
            $prices['taxAmount'] = (float) $totalsAddress->getTaxAmount();
            $prices['baseTaxAmount'] = (float) $totalsAddress->getBaseTaxAmount();
        }
        if ($shippingAddress) {
            $prices['shippingAmount'] = $shippingAddress->getShippingAmount()
                ? (float) $shippingAddress->getShippingAmount()
                : null;
            $prices['shippingAmountInclTax'] = $shippingAddress->getShippingInclTax()
                ? (float) $shippingAddress->getShippingInclTax()
                : null;
            $prices['shippingTaxAmount'] = $shippingAddress->getShippingTaxAmount()
                ? (float) $shippingAddress->getShippingTaxAmount()
                : null;
            $prices['baseShippingAmount'] = $shippingAddress->getBaseShippingAmount()
                ? (float) $shippingAddress->getBaseShippingAmount()
                : null;
        }

        return $prices;
    }

    /**
     * Get available shipping methods for address
     *
     * @return array<array{code: string, title: string, carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}>
     */
    public function getAvailableShippingMethods(\Mage_Sales_Model_Quote_Address $address): array
    {
        $methods = [];

        try {
            $address->collectShippingRates();

            // Rate prices are website base currency; the shipping total collector
            // converts the selected one into shipping_amount, so convert here too
            // or the same method would change price once selected.
            $store = $address->getQuote()->getStore();

            foreach ($address->getAllShippingRates() as $rate) {
                $carrierCode = (string) $rate->getCarrier();
                $methodCode = (string) $rate->getMethod();
                $carrierTitle = (string) $rate->getCarrierTitle();
                $methodTitle = (string) $rate->getMethodTitle();
                $methods[] = [
                    // `code`/`title` are the flat, client-facing pair (carrier_method
                    // and a human label); carrier/method parts are kept for callers
                    // that need them separately (e.g. setShippingMethodOnCart).
                    'code' => $carrierCode . '_' . $methodCode,
                    'title' => trim($carrierTitle . ' - ' . $methodTitle, ' -'),
                    'carrierCode' => $carrierCode,
                    'methodCode' => $methodCode,
                    'carrierTitle' => $carrierTitle,
                    'methodTitle' => $methodTitle,
                    'price' => (float) $store->convertPrice((float) $rate->getPrice(), false),
                ];
            }
        } catch (\Exception $e) {
            \Mage::log('Error getting shipping methods: ' . $e->getMessage(), \Mage::LOG_ERROR);
        }

        return $methods;
    }

    /**
     * Parse selected shipping method from address
     *
     * @return array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}|null
     */
    public function parseShippingMethod(\Mage_Sales_Model_Quote_Address $address): ?array
    {
        $shippingMethod = $address->getShippingMethod();
        if (!$shippingMethod) {
            return null;
        }

        $parts = explode('_', $shippingMethod, 2);
        if (count($parts) < 2) {
            return null;
        }

        return [
            'carrierCode' => $parts[0],
            'methodCode' => $parts[1],
            'carrierTitle' => $address->getShippingDescription() ?? '',
            'methodTitle' => $address->getShippingDescription() ?? '',
            'price' => (float) $address->getShippingAmount(),
        ];
    }

    /**
     * Get available payment methods for quote
     *
     * @return array<array{code: string, title: string, sortOrder: int, isOffline: bool}>
     */
    public function getAvailablePaymentMethods(\Mage_Sales_Model_Quote $quote): array
    {
        $methods = [];

        try {
            $store = $quote->getStore();
            $availableMethods = \Mage::helper('payment')->getStoreMethods($store, $quote);

            foreach ($availableMethods as $method) {
                if ($method->canUseForCountry($quote->getBillingAddress()->getCountry())) {
                    $methods[] = [
                        'code' => $method->getCode(),
                        'title' => $method->getTitle(),
                        'sortOrder' => (int) $method->getConfigData('sort_order'),
                        // Offline methods declare <group>offline</group> in config
                        // (checkmo, banktransfer, cashondelivery, purchaseorder, free).
                        'isOffline' => $method->getConfigData('group') === 'offline',
                    ];
                }
            }

            usort($methods, fn(array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);
        } catch (\Exception $e) {
            \Mage::log('Error getting payment methods: ' . $e->getMessage(), \Mage::LOG_ERROR);
        }

        return $methods;
    }


    /**
     * Get configured product options for a cart item (works for all product types)
     *
     * @return array<array{label: string, value: string}>
     */
    private function getItemConfigurationOptions(\Mage_Sales_Model_Quote_Item $item): array
    {
        try {
            $typeId = $item->getProductType() ?: $item->getProduct()->getTypeId();

            $rawOptions = match ($typeId) {
                \Mage_Catalog_Model_Product_Type::TYPE_BUNDLE => \Mage::helper('bundle/catalog_product_configuration')->getOptions($item),
                \Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE => \Mage::helper('downloadable/catalog_product_configuration')->getOptions($item),
                default => \Mage::helper('catalog/product_configuration')->getOptions($item),
            };

            $options = [];
            foreach ($rawOptions as $option) {
                $label = $option['label'] ?? '';
                $value = $option['value'] ?? '';

                if (is_array($value)) {
                    $value = implode(', ', array_map(fn($v) => strip_tags((string) $v), $value));
                } else {
                    $value = strip_tags((string) $value);
                }

                if ($label !== '' && $value !== '') {
                    $options[] = ['label' => (string) $label, 'value' => $value];
                }
            }

            return $options;
        } catch (\Throwable $e) {
            \Mage::log('Error getting item configuration options: ' . $e->getMessage(), \Mage::LOG_WARNING);
            return [];
        }
    }
}
