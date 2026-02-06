<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service\GraphQL;

use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Service\CartService;

/**
 * Cart Mutation Handler
 *
 * Handles all cart-related GraphQL operations for admin API.
 * Extracted from AdminGraphQlController for better code organization.
 */
class CartMutationHandler
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Handle getCart query
     */
    public function handleGetCart(array $variables): array
    {
        $cartId = $variables['cartId'] ?? $variables['id'] ?? null;
        $maskedId = $variables['maskedId'] ?? null;
        $quote = $this->cartService->getCart($cartId ? (int) $cartId : null, $maskedId);
        return ['cart' => $quote ? $this->mapCart($quote) : null];
    }

    /**
     * Handle createCart mutation
     */
    public function handleCreateCart(array $variables, array $context): array
    {
        $customerId = $variables['customerId'] ?? $context['customer_id'] ?? null;
        $storeId = $variables['storeId'] ?? $context['store_id'] ?? 1;
        $result = $this->cartService->createEmptyCart($customerId, $storeId);
        $mapped = $this->mapCart($result['quote']);
        if (!empty($result['maskedId'])) {
            $mapped['maskedId'] = $result['maskedId'];
        }
        return ['createCart' => $mapped];
    }

    /**
     * Handle addToCart mutation
     */
    public function handleAddToCart(array $variables): array
    {
        $cartId = $variables['cartId'] ?? $variables['input']['cartId'] ?? null;
        $sku = $variables['sku'] ?? $variables['input']['sku'] ?? null;
        $qty = $variables['qty'] ?? $variables['input']['qty'] ?? 1;
        $fulfillmentType = strtoupper($variables['fulfillmentType'] ?? $variables['input']['fulfillmentType'] ?? 'SHIP');

        // Validate fulfillment type
        if (!in_array($fulfillmentType, ['SHIP', 'PICKUP'], true)) {
            $fulfillmentType = 'SHIP';
        }

        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $quote = $this->cartService->addItem($quote, $sku, (float) $qty);

        // Set fulfillment type on the newly added item
        if ($fulfillmentType !== 'SHIP') {
            // Find the item we just added (by SKU, get the last one in case of duplicates)
            $addedItem = null;
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getSku() === $sku) {
                    $addedItem = $item;
                }
            }
            if ($addedItem) {
                $this->setItemFulfillmentType($addedItem, $fulfillmentType);
            }
        }

        return ['addToCart' => $this->mapCart($quote)];
    }

    /**
     * Handle updateQty mutation
     */
    public function handleUpdateQty(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        $itemId = $variables['itemId'] ?? null;
        $qty = $variables['qty'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$itemId) {
            throw ValidationException::requiredField('itemId');
        }
        if ($qty === null) {
            throw ValidationException::requiredField('qty');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $quote = $this->cartService->updateItem($quote, (int) $itemId, (float) $qty);
        return ['updateQty' => $this->mapCart($quote)];
    }

    /**
     * Handle removeItem mutation
     */
    public function handleRemoveItem(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        $itemId = $variables['itemId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$itemId) {
            throw ValidationException::requiredField('itemId');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $quote = $this->cartService->removeItem($quote, (int) $itemId);
        return ['removeItem' => $this->mapCart($quote)];
    }

    /**
     * Handle setItemFulfillment mutation
     */
    public function handleSetItemFulfillment(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        $itemId = $variables['itemId'] ?? null;
        $fulfillmentType = strtoupper($variables['fulfillmentType'] ?? 'SHIP');

        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$itemId) {
            throw ValidationException::requiredField('itemId');
        }

        // Validate fulfillment type
        if (!in_array($fulfillmentType, ['SHIP', 'PICKUP'], true)) {
            throw ValidationException::invalidValue('fulfillmentType', 'must be SHIP or PICKUP');
        }

        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        // Find the item
        $targetItem = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int) $item->getId() === (int) $itemId) {
                $targetItem = $item;
                break;
            }
        }

        if (!$targetItem) {
            throw NotFoundException::cartItem((int) $itemId);
        }

        // Set the fulfillment type
        $this->setItemFulfillmentType($targetItem, $fulfillmentType);

        return ['setItemFulfillment' => $this->mapCart($quote)];
    }

    /**
     * Handle applyCoupon mutation
     */
    public function handleApplyCoupon(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        $couponCode = $variables['couponCode'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$couponCode) {
            throw ValidationException::requiredField('couponCode');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        // First, check if this is a gift card code
        /** @phpstan-ignore-next-line */
        $giftcard = \Mage::getModel('maho_giftcard/giftcard')->loadByCode($couponCode);
        if ($giftcard->getId() && $giftcard->isValid()) {
            // It's a valid gift card - apply it
            /** @phpstan-ignore-next-line */
            \Mage::helper('maho_giftcard')->applyGiftcardToQuote($quote, $giftcard, null);
            $quote->collectTotals()->save();
            return ['applyCoupon' => $this->mapCart($quote)];
        }

        // Not a gift card, try as coupon
        try {
            $this->cartService->applyCoupon($quote, $couponCode);
        } catch (\Exception $e) {
            throw ValidationException::invalidValue('couponCode', 'invalid or expired coupon');
        }

        $quote->collectTotals();
        return ['applyCoupon' => $this->mapCart($quote)];
    }

    /**
     * Handle removeCoupon mutation
     */
    public function handleRemoveCoupon(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $this->cartService->removeCoupon($quote);
        $quote->collectTotals();
        return ['removeCoupon' => $this->mapCart($quote)];
    }

    /**
     * Handle assignCustomerToCart mutation
     */
    public function handleAssignCustomer(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        $customerId = $variables['customerId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw NotFoundException::customer($customerId);
        }
        $quote->assignCustomer($customer);
        $quote->collectTotals();
        $quote->save();
        return ['assignCustomerToCart' => $this->mapCart($quote)];
    }

    /**
     * Handle checkGiftCardBalance query
     */
    public function handleCheckGiftCardBalance(array $variables): array
    {
        $code = $variables['code'] ?? null;
        if (!$code) {
            throw ValidationException::requiredField('code');
        }
        /** @phpstan-ignore-next-line */
        $giftcard = \Mage::getModel('maho_giftcard/giftcard')->loadByCode($code);
        if (!$giftcard->getId()) {
            throw NotFoundException::giftCard($code);
        }
        return ['checkGiftCardBalance' => [
            'code' => $giftcard->getCode(),
            'balance' => [
                'value' => (float) $giftcard->getBalance(),
                'formatted' => \Mage::helper('core')->currency($giftcard->getBalance(), true, false),
            ],
            'status' => $giftcard->getStatus(),
            'isValid' => $giftcard->isValid(),
            'expiresAt' => $giftcard->getExpiresAt(),
        ]];
    }

    /**
     * Handle applyGiftCard mutation
     */
    public function handleApplyGiftCard(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        // Accept both 'code' and 'giftcardCode' parameter names
        $code = $variables['code'] ?? $variables['giftcardCode'] ?? null;
        $amount = isset($variables['amount']) ? (float) $variables['amount'] : null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$code) {
            throw ValidationException::requiredField('code');
        }

        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        /** @phpstan-ignore-next-line */
        $giftcard = \Mage::getModel('maho_giftcard/giftcard')->loadByCode($code);
        if (!$giftcard->getId() || !$giftcard->isValid()) {
            throw ValidationException::invalidValue('code', 'invalid or expired gift card');
        }

        /** @phpstan-ignore-next-line */
        \Mage::helper('maho_giftcard')->applyGiftcardToQuote($quote, $giftcard, $amount);
        $quote->collectTotals()->save();

        // Reload quote to get fresh totals
        $quote = $this->cartService->getCart((int) $cartId);
        return ['applyGiftcardToCart' => $this->mapCart($quote)];
    }

    /**
     * Handle removeGiftCard mutation
     */
    public function handleRemoveGiftCard(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        // Accept both 'code' and 'giftcardCode' parameter names
        $code = $variables['code'] ?? $variables['giftcardCode'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$code) {
            throw ValidationException::requiredField('code');
        }

        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        /** @phpstan-ignore-next-line */
        \Mage::helper('maho_giftcard')->removeGiftcardFromQuote($quote, $code);
        $quote->collectTotals()->save();

        // Reload quote to get fresh totals
        $quote = $this->cartService->getCart((int) $cartId);
        return ['removeGiftcardFromCart' => $this->mapCart($quote)];
    }

    /**
     * Handle availableShippingMethods query
     */
    public function handleShippingMethods(array $variables): array
    {
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }

        // Load quote without store filtering for admin/POS context
        $quote = \Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);

        if (!$quote || !$quote->getId()) {
            throw NotFoundException::cart($cartId);
        }

        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress->getCountryId()) {
            $shippingAddress->setCountryId('AU')->setPostcode('3000')->setRegionId(574)->setCollectShippingRates(1);
        }

        $shippingAddress->collectShippingRates();
        $rates = $shippingAddress->getGroupedAllShippingRates();

        $methods = [];
        foreach ($rates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methods[] = [
                    'carrierCode' => $rate->getCarrier(),
                    'carrierTitle' => $rate->getCarrierTitle(),
                    'methodCode' => $rate->getMethod(),
                    'methodTitle' => $rate->getMethodTitle(),
                    'amount' => ['value' => (float) $rate->getPrice(), 'formatted' => \Mage::helper('core')->currency($rate->getPrice(), true, false)],
                    'available' => !$rate->getErrorMessage(),
                    'errorMessage' => $rate->getErrorMessage(),
                ];
            }
        }

        // Ensure freeshipping for POS
        $hasFreeShipping = false;
        foreach ($methods as $m) {
            if ($m['carrierCode'] === 'freeshipping') {
                $hasFreeShipping = true;
                break;
            }
        }
        if (!$hasFreeShipping) {
            array_unshift($methods, [
                'carrierCode' => 'freeshipping', 'carrierTitle' => 'Free Shipping',
                'methodCode' => 'freeshipping', 'methodTitle' => 'POS In-Store Pickup',
                'amount' => ['value' => 0, 'formatted' => '$0.00'],
                'available' => true, 'errorMessage' => null,
            ]);
        }

        return ['availableShippingMethods' => $methods];
    }

    /**
     * Map cart/quote to array format
     *
     * @param \Mage_Sales_Model_Quote|array $quote
     */
    public function mapCart($quote): array
    {
        if (is_array($quote)) {
            return $quote;
        }

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'id' => (int) $item->getId(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => (float) $item->getQty(),
                'price' => (float) $item->getPrice(),
                'rowTotal' => (float) $item->getRowTotal(),
                'discountAmount' => (float) $item->getDiscountAmount(),
                'fulfillmentType' => $this->getItemFulfillmentType($item),
            ];
        }

        return [
            'id' => (int) $quote->getId(),
            'maskedId' => base64_encode('cart_' . $quote->getId() . '_' . substr(md5($quote->getId() . $quote->getCreatedAt()), 0, 8)),
            'customerId' => $quote->getCustomerId() ? (int) $quote->getCustomerId() : null,
            'storeId' => (int) $quote->getStoreId(),
            'isActive' => (bool) $quote->getIsActive(),
            'items' => $items,
            'itemsCount' => (int) $quote->getItemsCount(),
            'itemsQty' => (float) $quote->getItemsQty(),
            'prices' => [
                'subtotal' => (float) $quote->getSubtotal(),
                'grandTotal' => (float) $quote->getGrandTotal(),
                'discountAmount' => abs((float) ($quote->getShippingAddress()->getDiscountAmount() ?: 0)),
                'taxAmount' => (float) $quote->getShippingAddress()->getTaxAmount(),
                'shippingAmount' => (float) $quote->getShippingAddress()->getShippingAmount(),
                'giftcardAmount' => abs((float) ($quote->getGiftcardAmount() ?: 0)),
            ],
            'appliedCoupon' => $quote->getCouponCode() ? ['code' => $quote->getCouponCode()] : null,
            'appliedGiftcards' => $this->mapAppliedGiftcards($quote),
            'currency' => $quote->getQuoteCurrencyCode() ?: 'AUD',
        ];
    }

    /**
     * Map applied gift cards from quote
     */
    private function mapAppliedGiftcards(\Mage_Sales_Model_Quote $quote): array
    {
        $giftcards = [];

        // Get gift card data from quote's giftcard_codes field
        $giftcardCodes = $quote->getGiftcardCodes();
        if (!$giftcardCodes) {
            return $giftcards;
        }

        $codesData = json_decode($giftcardCodes, true);
        if (!is_array($codesData)) {
            return $giftcards;
        }

        foreach ($codesData as $code => $appliedAmount) {
            /** @phpstan-ignore-next-line */
            $giftcard = \Mage::getModel('maho_giftcard/giftcard')->loadByCode($code);
            if ($giftcard->getId()) {
                $giftcards[] = [
                    'code' => $code,
                    'appliedAmount' => [
                        'value' => (float) $appliedAmount,
                        'formatted' => \Mage::helper('core')->currency($appliedAmount, true, false),
                    ],
                    'balance' => [
                        'value' => (float) $giftcard->getBalance(),
                        'formatted' => \Mage::helper('core')->currency($giftcard->getBalance(), true, false),
                    ],
                ];
            }
        }

        return $giftcards;
    }

    /**
     * Set fulfillment type on a quote item using additional_data field
     */
    private function setItemFulfillmentType(\Mage_Sales_Model_Quote_Item $item, string $fulfillmentType): void
    {
        $additionalData = $item->getAdditionalData();
        $data = $additionalData ? json_decode($additionalData, true) : [];

        if (!is_array($data)) {
            $data = [];
        }

        $data['fulfillment_type'] = $fulfillmentType;

        $item->setAdditionalData(json_encode($data));
        $item->save();
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

        return 'SHIP'; // Default
    }
}
