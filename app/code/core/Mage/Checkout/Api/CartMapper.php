<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Checkout\Api;

use Mage\Customer\Api\Address;

/**
 * Shared cart-to-DTO mapping logic used by both CartProvider and CartProcessor
 */
class CartMapper
{

    /**
     * Map Maho quote model to Cart DTO
     */
    public function mapQuoteToCart(\Mage_Sales_Model_Quote $quote, bool $collectTotals = true): Cart
    {
        if ($collectTotals) {
            $quote->collectTotals();
        }

        $cart = new Cart();
        $cart->id = (int) $quote->getId();
        $cart->maskedId = $quote->getData('masked_quote_id');
        $cart->customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $cart->storeId = (int) $quote->getStoreId();
        $cart->isActive = (bool) $quote->getIsActive();
        $cart->currency = $quote->getQuoteCurrencyCode() ?: \Mage::app()->getStore()->getDefaultCurrencyCode();
        $cart->itemsCount = (int) $quote->getItemsCount();
        $cart->itemsQty = (float) $quote->getItemsQty();
        $cart->createdAt = $quote->getCreatedAt();
        $cart->updatedAt = $quote->getUpdatedAt();

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
            } catch (\Exception $e) {
                $cart->selectedPaymentMethod = [
                    'code' => $payment->getMethod(),
                    'title' => $payment->getMethod(),
                ];
            }
        }

        // Get applied coupon
        $couponCode = $quote->getCouponCode();
        if ($couponCode) {
            $cart->appliedCoupon = [
                'code' => $couponCode,
                'discountAmount' => (float) abs($shippingAddress ? $shippingAddress->getDiscountAmount() : 0),
            ];
        }

        // Get applied gift cards
        $giftcardCodesJson = $quote->getData('giftcard_codes');
        if ($giftcardCodesJson) {
            $giftcardCodes = json_decode($giftcardCodesJson, true);
            if (is_array($giftcardCodes)) {
                foreach ($giftcardCodes as $code => $balance) {
                    $cart->appliedGiftcards[] = [
                        'code' => (string) $code,
                        'balance' => (float) $balance,
                        'appliedAmount' => 0.0,
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
        $dto->price = (float) $item->getPrice();
        $dto->priceInclTax = (float) $item->getPriceInclTax();
        $dto->rowTotal = (float) $item->getRowTotal();
        $dto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
        $dto->rowTotalWithDiscount = (float) ($item->getRowTotal() - $item->getDiscountAmount());
        $dto->discountAmount = $item->getDiscountAmount() ? (float) $item->getDiscountAmount() : null;
        $dto->discountPercent = $item->getDiscountPercent() ? (float) $item->getDiscountPercent() : null;
        $dto->taxAmount = $item->getTaxAmount() ? (float) $item->getTaxAmount() : null;
        $dto->taxPercent = $item->getTaxPercent() ? (float) $item->getTaxPercent() : null;
        $dto->productId = $item->getProductId() ? (int) $item->getProductId() : null;
        $dto->productType = $item->getProductType();
        $dto->thumbnailUrl = $preloadedThumbnailUrl;
        $dto->stockStatus = $preloadedStockStatus;
        $dto->fulfillmentType = $this->getItemFulfillmentType($item);

        // Get configured product options for display
        $dto->options = $this->getItemConfigurationOptions($item);

        \Mage::dispatchEvent('api_cart_item_dto_build', ['item' => $item, 'dto' => $dto]);
        return $dto;
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
     * Map Maho quote to prices array
     */
    public function mapPricesToArray(\Mage_Sales_Model_Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();

        $prices = [
            'subtotal' => (float) $quote->getSubtotal(),
            'subtotalInclTax' => (float) array_reduce($quote->getAllVisibleItems(), fn(float $sum, $item) => $sum + (float) $item->getRowTotalInclTax(), 0.0),
            'subtotalWithDiscount' => (float) $quote->getSubtotalWithDiscount(),
            'discountAmount' => null,
            'shippingAmount' => null,
            'shippingAmountInclTax' => null,
            'taxAmount' => 0.0,
            'grandTotal' => (float) $quote->getGrandTotal(),
            'giftcardAmount' => null,
        ];

        if ($shippingAddress) {
            $prices['discountAmount'] = $shippingAddress->getDiscountAmount()
                ? (float) abs($shippingAddress->getDiscountAmount())
                : null;
            $prices['shippingAmount'] = $shippingAddress->getShippingAmount()
                ? (float) $shippingAddress->getShippingAmount()
                : null;
            $prices['shippingAmountInclTax'] = $shippingAddress->getShippingInclTax()
                ? (float) $shippingAddress->getShippingInclTax()
                : null;
            $prices['taxAmount'] = (float) $shippingAddress->getTaxAmount();
        }

        return $prices;
    }

    /**
     * Get available shipping methods for address
     *
     * @return array<array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}>
     */
    public function getAvailableShippingMethods(\Mage_Sales_Model_Quote_Address $address): array
    {
        $methods = [];

        try {
            $address->collectShippingRates();

            foreach ($address->getAllShippingRates() as $rate) {
                $methods[] = [
                    'carrierCode' => $rate->getCarrier(),
                    'methodCode' => $rate->getMethod(),
                    'carrierTitle' => $rate->getCarrierTitle(),
                    'methodTitle' => $rate->getMethodTitle(),
                    'price' => (float) $rate->getPrice(),
                ];
            }
        } catch (\Exception $e) {
            \Mage::log('Error getting shipping methods: ' . $e->getMessage());
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
     * @return array<array{code: string, title: string}>
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
                    ];
                }
            }
        } catch (\Exception $e) {
            \Mage::log('Error getting payment methods: ' . $e->getMessage());
        }

        return $methods;
    }

    /**
     * Get fulfillment type from a quote item's additional_data
     */
    private function getItemFulfillmentType(\Mage_Sales_Model_Quote_Item $item): string
    {
        $additionalData = $item->getAdditionalData();

        if ($additionalData) {
            $data = json_decode($additionalData, true);
            if (is_array($data) && isset($data['fulfillment_type'])) {
                return strtoupper($data['fulfillment_type']);
            }
        }

        return 'SHIP';
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
