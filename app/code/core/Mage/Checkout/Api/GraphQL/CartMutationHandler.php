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

namespace Mage\Checkout\Api\GraphQL;

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;
use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;

/**
 * Cart Mutation Handler
 *
 * Handles all cart-related GraphQL operations for admin API.
 * Uses CartMapper::mapQuoteToCart() for DTO building to ensure
 * events (api_cart_dto_build) and extensions fire consistently.
 */
class CartMutationHandler
{
    private CartService $cartService;
    private CartMapper $cartMapper;

    public function __construct(CartService $cartService, CartMapper $cartMapper)
    {
        $this->cartService = $cartService;
        $this->cartMapper = $cartMapper;
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
        /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($couponCode);
        if ($giftcard->getId() && $giftcard->isValid()) {
            // It's a valid gift card - apply it to quote via giftcard_codes field
            $this->applyGiftcardToQuote($quote, $giftcard);
            $quote->collectTotals()->save();
            return ['applyCoupon' => $this->mapCart($quote)];
        }

        // Not a gift card, try as coupon
        try {
            $this->cartService->applyCoupon($quote, $couponCode);
        } catch (\Exception $e) {
            \Mage::logException($e);
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
        /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
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

        /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
        if (!$giftcard->getId() || !$giftcard->isValid()) {
            throw ValidationException::invalidValue('code', 'invalid or expired gift card');
        }

        $this->applyGiftcardToQuote($quote, $giftcard, $amount);
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

        $this->removeGiftcardFromQuote($quote, $code);
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
            $defaults = \Maho\ApiPlatform\Service\StoreDefaults::getPosAddress($quote->getStoreId() ? (int) $quote->getStoreId() : null);
            $shippingAddress->setCountryId($defaults['country_id'])->setPostcode($defaults['postcode'])->setRegionId($defaults['region_id'])->setCollectShippingRates(1);
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

        // Use CartMapper for consistent DTO building (fires api_cart_dto_build + api_cart_item_dto_build)
        $dto = $this->cartMapper->mapQuoteToCart($quote);
        $data = $dto->toArray();

        // Add GraphQL-specific fields
        $data['maskedId'] = base64_encode('cart_' . $quote->getId() . '_' . substr(md5($quote->getId() . $quote->getCreatedAt()), 0, 8));

        // Enrich items with fulfillment type (GraphQL-specific) — align by item ID
        $quoteItemsById = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $quoteItemsById[(int) $item->getId()] = $item;
        }
        foreach ($data['items'] as &$itemData) {
            $itemId = (int) ($itemData['id'] ?? 0);
            if (isset($quoteItemsById[$itemId])) {
                $itemData['fulfillmentType'] = $this->getItemFulfillmentType($quoteItemsById[$itemId]);
            }
        }
        unset($itemData);

        // Add giftcard data (GraphQL-specific)
        $data['appliedGiftcards'] = $this->mapAppliedGiftcards($quote);

        return $data;
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
            /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
            $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode((string) $code);
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
     * Apply a gift card to a quote by storing its code and amount in giftcard_codes
     */
    private function applyGiftcardToQuote(\Mage_Sales_Model_Quote $quote, \Maho_Giftcard_Model_Giftcard $giftcard, ?float $amount = null): void
    {
        $codesJson = $quote->getGiftcardCodes();
        $codes = $codesJson ? (array) json_decode($codesJson, true) : [];

        $applyAmount = $amount ?? $giftcard->getBalance();
        $codes[$giftcard->getCode()] = $applyAmount;

        $quote->setGiftcardCodes(json_encode($codes));
    }

    /**
     * Remove a gift card from a quote by its code
     */
    private function removeGiftcardFromQuote(\Mage_Sales_Model_Quote $quote, string $code): void
    {
        $codesJson = $quote->getGiftcardCodes();
        $codes = $codesJson ? (array) json_decode($codesJson, true) : [];

        unset($codes[$code]);

        $quote->setGiftcardCodes(empty($codes) ? null : json_encode($codes));
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
