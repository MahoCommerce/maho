<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use Maho\ApiPlatform\Service\StoreDefaults;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cart Service - Business logic for cart operations
 */
class CartService
{
    private const MAX_ITEM_QTY = 10000;

    /**
     * Create empty cart
     *
     * @param int|null $customerId Customer ID (null for guest)
     * @param int|null $storeId Store ID
     * @return array [quote, maskedId]
     */
    public function createEmptyCart(?int $customerId = null, ?int $storeId = null): array
    {
        $quote = \Mage::getModel('sales/quote');

        if ($storeId) {
            $quote->setStoreId($storeId);
        } else {
            // Use the default store, Mage::app()->getStore() returns admin (0) under Symfony
            $defaultStore = \Mage::app()->getDefaultStoreView();
            if (!$defaultStore) {
                throw new \RuntimeException('No default store view configured');
            }
            $quote->setStoreId((int) $defaultStore->getId() ?: 1);
        }

        if ($customerId) {
            $quote->setCustomerId($customerId);
            $quote->setCustomerIsGuest(0);
        } else {
            $quote->setCustomerIsGuest(1);
        }

        $quote->setIsActive(1);

        // Generate and set masked ID (used by storefront to reference the cart)
        $maskedId = $this->generateSecureMaskedId();
        $quote->setData('masked_quote_id', $maskedId);

        $quote->save();

        return ['quote' => $quote, 'maskedId' => $maskedId];
    }

    /**
     * Get cart by ID or masked ID
     *
     * @param int|null $cartId Cart ID
     * @param string|null $maskedId Masked ID
     */
    public function getCart(?int $cartId = null, ?string $maskedId = null): ?\Mage_Sales_Model_Quote
    {
        if ($maskedId) {
            $cartId = $this->getCartIdFromMaskedId($maskedId);
        }

        if (!$cartId) {
            return null;
        }

        // Load quote - use loadByIdWithoutStore to avoid store filtering issues in admin context
        /** @var \Mage_Sales_Model_Quote $quote */
        $quote = \Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);

        if (!$quote->getId()) {
            return null;
        }

        // Ensure quote is loaded with its store context (important when called from admin)
        if ($quote->getStoreId()) {
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        // Collect totals with manual fallback for admin context
        $this->collectAndVerifyTotals($quote);

        return $quote;
    }

    /**
     * Get customer's active cart
     *
     * @param int $customerId Customer ID
     */
    public function getCustomerCart(int $customerId): \Mage_Sales_Model_Quote
    {
        $quote = \Mage::getModel('sales/quote')
            ->loadByCustomer($customerId);

        if (!$quote->getId()) {
            // Create new cart for customer
            $result = $this->createEmptyCart($customerId);
            $quote = $result['quote'];
        }

        return $quote;
    }

    /**
     * Resolve a cart from API request context.
     * Handles both /carts/{id} (numeric) and /guest-carts/{maskedId} (hex) patterns.
     *
     * @return array{quote: \Mage_Sales_Model_Quote|null, accessedByMaskedId: bool}
     */
    public function resolveCartFromRequest(
        array $uriVariables,
        array $context,
    ): array {
        $request = $context['request'] ?? null;
        $args = $context['args']['input'] ?? $context['args'] ?? [];

        // Bridge REST request body for Provider context (Processor does this later, but Provider runs first)
        if (empty($args) && $request instanceof \Symfony\Component\HttpFoundation\Request) {
            $body = json_decode((string) $request->getContent(), true);
            if (is_array($body)) {
                $args = $body;
            }
        }

        // Priority 1: maskedId from GraphQL args or REST body
        $maskedId = $args['maskedId'] ?? null;

        // Priority 2: maskedId from REST guest-cart URI (regex to bypass int cast)
        if (!$maskedId && $request instanceof \Symfony\Component\HttpFoundation\Request) {
            if (preg_match('#/guest-carts/([a-f0-9]{32})#i', $request->getPathInfo(), $m)) {
                $maskedId = $m[1];
            }
        }

        // Priority 3: cartId from GraphQL args or uriVariables
        $cartId = isset($args['cartId']) ? (int) $args['cartId'] : null;
        if (!$cartId && !$maskedId && isset($uriVariables['id'])) {
            $cartId = (int) $uriVariables['id'];
        }

        if (!$maskedId && !$cartId) {
            return ['quote' => null, 'accessedByMaskedId' => false];
        }

        $quote = $this->getCart($cartId, $maskedId);
        return ['quote' => $quote, 'accessedByMaskedId' => $maskedId !== null];
    }

    /**
     * Verify the caller has access to this cart.
     *
     * @throws AccessDeniedHttpException
     */
    public function verifyCartAccess(
        \Mage_Sales_Model_Quote $quote,
        bool $accessedByMaskedId,
        ?int $authenticatedCustomerId,
        bool $isPrivileged = false,
    ): void {
        // Admins, POS users, API users can access any cart
        if ($isPrivileged) {
            return;
        }

        $cartCustomerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;

        // Customer-owned cart: verify ownership
        if ($cartCustomerId !== null) {
            if ($authenticatedCustomerId === null || $cartCustomerId !== $authenticatedCustomerId) {
                throw new AccessDeniedHttpException('You can only access your own cart');
            }
            return;
        }

        // Guest cart: only the masked ID grants access. The numeric /carts/{id}
        // path is enumerable, so even authenticated customers must not see
        // someone else's pre-login cart through it.
        if (!$accessedByMaskedId) {
            throw new AccessDeniedHttpException('Guest carts must be accessed via masked ID');
        }
    }

    /**
     * Add item to cart
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param string $sku Product SKU
     * @param float $qty Quantity
     * @param array $options Custom options (option_id => value)
     */
    public function addItem(\Mage_Sales_Model_Quote $quote, string $sku, float $qty, array $options = []): \Mage_Sales_Model_Quote
    {
        // Validate quantity
        if ($qty <= 0) {
            throw new \RuntimeException('Quantity must be greater than zero');
        }
        if ($qty > self::MAX_ITEM_QTY) {
            throw new \RuntimeException('Quantity cannot exceed 10,000');
        }

        // First find product ID by SKU
        $productId = \Mage::getResourceModel('catalog/product')->getIdBySku($sku);

        if (!$productId) {
            throw new \RuntimeException("Product with SKU '{$sku}' not found");
        }

        $this->logDebug("Adding product {$sku} (ID: {$productId}) to quote {$quote->getId()}, quote store_id: {$quote->getStoreId()}");

        // Load product in the quote's store context to get correct prices
        $product = \Mage::getModel('catalog/product')
            ->setStoreId($quote->getStoreId())
            ->load($productId);

        if (!$product->getId()) {
            throw new \RuntimeException("Product with SKU '{$sku}' not found");
        }

        // Status gate, addProduct does not check this itself, so without an
        // explicit guard a disabled SKU is addable through the public API.
        if ((int) $product->getStatus() !== \Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
            throw new \RuntimeException("Product '{$sku}' is not available");
        }

        // Visibility gate: refuse 'not_visible_individually' simples that
        // aren't part of a configurable. Variants of a configurable are
        // legitimately not-visible-individually and are auto-promoted to the
        // configurable parent below.
        $visibility = (int) $product->getVisibility();
        if ($visibility === \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
            && $product->getTypeId() !== \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
        ) {
            throw new \RuntimeException("Product '{$sku}' is not available");
        }

        // Check if this simple product is a child of a configurable
        // If so, add the configurable parent with the proper super_attribute options
        $buyRequest = new \Maho\DataObject(['qty' => $qty]);

        // Add custom options, downloadable links, grouped, and bundle params if provided
        // Supports two formats:
        //   Structured: ['options' => [...], 'links' => [...], 'super_group' => [...], 'bundle_option' => [...]] (from GraphQL CartProcessor)
        //   Flat: [optionId => valueId, ...] (from REST GuestCartController)
        if (!empty($options)) {
            if (isset($options['options']) || isset($options['links']) || isset($options['super_group']) || isset($options['bundle_option'])) {
                // Structured format
                if (!empty($options['options'])) {
                    $buyRequest->setData('options', $options['options']);
                }
                if (!empty($options['links'])) {
                    $buyRequest->setData('links', $options['links']);
                }
                if (!empty($options['super_group'])) {
                    $buyRequest->setData('super_group', $options['super_group']);
                }
                if (!empty($options['bundle_option'])) {
                    $buyRequest->setData('bundle_option', $options['bundle_option']);
                }
                if (!empty($options['bundle_option_qty'])) {
                    $buyRequest->setData('bundle_option_qty', $options['bundle_option_qty']);
                }
            } else {
                // Flat custom options format (REST)
                $buyRequest->setData('options', $options);
            }
        }

        // Only auto-promote a simple to its configurable parent when the simple
        // is not individually visible. A simple that *is* visible on its own
        // (e.g. POS adding a specific variant SKU) is sold as-is; otherwise we
        // resolve to the parent so the cart shows the configurable, matching
        // storefront behaviour.
        if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
            && (int) $product->getVisibility() === \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
        ) {
            $parentIds = \Mage::getModel('catalog/product_type_configurable')
                ->getParentIdsByChild((int) $productId);

            if (!empty($parentIds)) {
                $parentId = $parentIds[0];
                $this->logDebug("Simple product {$productId} has configurable parent {$parentId}, adding as configurable");

                // Load the configurable parent
                $configurableProduct = \Mage::getModel('catalog/product')
                    ->setStoreId($quote->getStoreId())
                    ->load($parentId);

                if ($configurableProduct->getId()) {
                    // Get the super_attribute values for this simple product
                    $superAttributes = $this->getSuperAttributesForSimple($configurableProduct, $product);

                    if (!empty($superAttributes)) {
                        // Build buy request with super_attribute
                        $buyRequest->setData('product', $parentId);
                        $buyRequest->setData('super_attribute', $superAttributes);

                        // Use the configurable product instead
                        $product = $configurableProduct;
                        $this->logDebug("Using configurable product {$parentId} with super_attributes: " . json_encode($superAttributes));
                    }
                }
            }
        }

        $this->logDebug("Product loaded: ID={$product->getId()}, StoreId={$product->getStoreId()}, Price={$product->getPrice()}, FinalPrice={$product->getFinalPrice()}");
        // Convert flat date/datetime values to the array format Magento expects
        $currentOptions = $buyRequest->getData('options') ?: [];
        if (!empty($currentOptions)) {
            foreach ($product->getOptions() as $opt) {
                $optId = (string) $opt->getId();
                if (!isset($currentOptions[$optId])) {
                    continue;
                }
                $val = $currentOptions[$optId];
                if (is_string($val) && in_array($opt->getType(), [
                    \Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE,
                    \Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME,
                    \Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME,
                ], true)) {
                    if ($opt->getType() === \Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE) {
                        $currentOptions[$optId] = ['date' => $val];
                    } elseif ($opt->getType() === \Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME) {
                        $currentOptions[$optId] = ['datetime' => $val];
                    } elseif ($opt->getType() === \Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME) {
                        $currentOptions[$optId] = ['time' => $val];
                    }
                }
            }
            $buyRequest->setData('options', $currentOptions);
        }

        // Inject API file uploads into buyRequest (prevents forgery, uses DataObject pattern)
        if (!empty($options['options_files'])) {
            $optionsFiles = [];
            foreach ($options['options_files'] as $optionId => $fileData) {
                $optionId = (int) $optionId;
                // Verify this option ID belongs to a file-type option on this product
                $productOption = $product->getOptionById((string) $optionId);
                if (!$productOption || $productOption->getType() !== \Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE) {
                    throw new \RuntimeException("Option ID {$optionId} is not a valid file-type option for this product");
                }
                if (!is_array($fileData) || empty($fileData['base64_encoded_data']) || empty($fileData['name'])) {
                    throw new \RuntimeException("File option {$optionId} requires 'name' and 'base64_encoded_data'");
                }
                $optionsFiles[$optionId] = $fileData;
            }
            if (!empty($optionsFiles)) {
                $buyRequest->setData('options_files', $optionsFiles);
            }
        }
        // Set store context before addProduct so item prices are calculated correctly
        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
        }

        $result = $quote->addProduct($product, $buyRequest);

        // addProduct returns a string error message on failure
        if (is_string($result)) {
            $this->logDebug("Failed to add product: {$result}");
            throw new \RuntimeException("Failed to add product: {$result}");
        }

        $this->collectAndVerifyTotals($quote);

        $quote->save();

        return $quote;
    }

    /**
     * Update cart item
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param int $itemId Item ID
     * @param float $qty New quantity
     */
    public function updateItem(\Mage_Sales_Model_Quote $quote, int $itemId, float $qty): \Mage_Sales_Model_Quote
    {
        // Validate quantity
        if ($qty <= 0) {
            throw new \RuntimeException('Quantity must be greater than zero');
        }
        if ($qty > self::MAX_ITEM_QTY) {
            throw new \RuntimeException('Quantity cannot exceed 10,000');
        }

        $item = $quote->getItemById($itemId);

        if (!$item) {
            throw new \RuntimeException("Cart item with ID '{$itemId}' not found");
        }

        $item->setQty($qty);

        $this->collectAndVerifyTotals($quote);

        $quote->save();

        return $quote;
    }

    /**
     * Remove item from cart
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param int $itemId Item ID
     */
    public function removeItem(\Mage_Sales_Model_Quote $quote, int $itemId): \Mage_Sales_Model_Quote
    {
        $quote->removeItem($itemId);
        $this->collectAndVerifyTotals($quote);

        $quote->save();

        return $quote;
    }

    /**
     * Apply coupon code
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param string $couponCode Coupon code
     */
    public function applyCoupon(\Mage_Sales_Model_Quote $quote, string $couponCode): \Mage_Sales_Model_Quote
    {
        // Validate coupon exists in the database before applying
        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon')->load($couponCode, 'code');
        if (!$coupon->getId()) {
            throw new \RuntimeException("Coupon code '{$couponCode}' is not valid");
        }

        $quote->setCouponCode($couponCode);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        // setCouponCode() persists the string even when the rule does not fire
        // (inactive/expired/exhausted/wrong website). Confirm the coupon's rule
        // actually applied by checking the rule id landed on a quote address.
        if ($quote->getCouponCode() !== $couponCode || !$this->isCouponRuleApplied($quote, (int) $coupon->getRuleId())) {
            $quote->setCouponCode('');
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals()->save();
            throw new \RuntimeException("Coupon code '{$couponCode}' could not be applied");
        }

        return $quote;
    }

    /**
     * Whether the given salesrule rule id fired on any quote address after
     * totals collection (i.e. the coupon genuinely applied).
     */
    private function isCouponRuleApplied(\Mage_Sales_Model_Quote $quote, int $ruleId): bool
    {
        if ($ruleId === 0) {
            return false;
        }
        foreach ($quote->getAllAddresses() as $address) {
            $ruleIds = $address->getAppliedRuleIds();
            $ruleIds = is_string($ruleIds) ? $ruleIds : '';
            $applied = array_filter(explode(',', $ruleIds), static fn(string $id): bool => $id !== '');
            if (in_array((string) $ruleId, $applied, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove coupon code
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     */
    public function removeCoupon(\Mage_Sales_Model_Quote $quote): \Mage_Sales_Model_Quote
    {
        $quote->setCouponCode('');
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        return $quote;
    }

    /**
     * Apply gift card to cart
     *
     * @throws \RuntimeException
     */
    public function applyGiftcard(\Mage_Sales_Model_Quote $quote, string $giftcardCode): \Mage_Sales_Model_Quote
    {
        if (!$giftcardCode) {
            throw new \RuntimeException('Gift card code is required');
        }

        // Check if cart has gift card products
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'giftcard') {
                throw new \RuntimeException('Gift cards cannot be used to purchase gift card products');
            }
        }

        // Load gift card by code
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($giftcardCode);

        if (!$giftcard->getId()) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not valid');
        }

        if (!$giftcard->isValid()) {
            $status = $giftcard->getStatus();
            if ($status === 'pending') {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" is pending activation');
            }
            if ($status === 'expired') {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" has expired');
            }
            if ($status === 'used') {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" has been fully used');
            }
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not active');
        }

        // Gift cards are scoped to the website that issued them
        if (!$giftcard->isValidForWebsite((int) $quote->getStore()->getWebsiteId())) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not valid for this store');
        }

        // Get currently applied codes
        $appliedCodes = $quote->getGiftcardCodes();
        $appliedCodes = $appliedCodes ? json_decode($appliedCodes, true) : [];

        // Check if already applied
        if (isset($appliedCodes[$giftcardCode])) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is already applied');
        }

        // Apply gift card - store max amount available (in quote currency)
        $quoteCurrency = $quote->getQuoteCurrencyCode();
        $appliedCodes[$giftcardCode] = $giftcard->getBalance($quoteCurrency);

        $quote->setGiftcardCodes(json_encode($appliedCodes));
        $quote->collectTotals()->save();

        return $quote;
    }

    /**
     * Re-validate every applied gift card against the live DB balance
     * immediately before order placement. The amount stored on the quote is
     * a snapshot from applyGiftcard(); without this re-check, a card that has
     * been spent elsewhere between apply and place would still discount the
     * order at its original (now stale) balance and the store would eat the
     * difference.
     *
     * @throws \RuntimeException when an applied card is no longer redeemable
     */
    public function revalidateGiftcards(\Mage_Sales_Model_Quote $quote): \Mage_Sales_Model_Quote
    {
        $applied = $quote->getGiftcardCodes();
        $applied = $applied ? json_decode($applied, true) : [];
        if (!$applied) {
            return $quote;
        }

        $changed = false;
        $quoteCurrency = $quote->getQuoteCurrencyCode();
        $websiteId = (int) $quote->getStore()->getWebsiteId();

        foreach ($applied as $code => $snapshotBalance) {
            $card = \Mage::getModel('giftcard/giftcard')->loadByCode((string) $code);
            if (!$card->getId() || !$card->isValidForWebsite($websiteId)) {
                throw new \RuntimeException('Gift card "' . $code . '" is no longer valid');
            }

            $live = (float) $card->getBalance($quoteCurrency);
            if ($live <= 0) {
                throw new \RuntimeException('Gift card "' . $code . '" has no remaining balance');
            }

            // Cap the applied amount at the live balance
            if ($live < (float) $snapshotBalance) {
                $applied[$code] = $live;
                $changed = true;
            }
        }

        if ($changed) {
            $quote->setGiftcardCodes(json_encode($applied));
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals()->save();
        }

        return $quote;
    }

    /**
     * Remove gift card from cart
     *
     * @throws \RuntimeException
     */
    public function removeGiftcard(\Mage_Sales_Model_Quote $quote, string $giftcardCode): \Mage_Sales_Model_Quote
    {
        if (!$giftcardCode) {
            throw new \RuntimeException('Gift card code is required');
        }

        // Get currently applied codes
        $appliedCodes = $quote->getGiftcardCodes();
        $appliedCodes = $appliedCodes ? json_decode($appliedCodes, true) : [];

        // Check if gift card is applied
        if (!isset($appliedCodes[$giftcardCode])) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not applied to this cart');
        }

        // Remove the code
        unset($appliedCodes[$giftcardCode]);

        if (empty($appliedCodes)) {
            $quote->setGiftcardCodes(null);
            $quote->setGiftcardAmount(0);
            $quote->setBaseGiftcardAmount(0);
        } else {
            $quote->setGiftcardCodes(json_encode($appliedCodes));
        }

        $quote->collectTotals()->save();

        return $quote;
    }

    /**
     * Set fulfillment type on a cart item (SHIP or PICKUP for BOPIS)
     *
     * @throws \RuntimeException
     */
    public function setItemFulfillmentType(\Mage_Sales_Model_Quote $quote, int $itemId, string $fulfillmentType): \Mage_Sales_Model_Quote
    {
        $fulfillmentType = strtoupper($fulfillmentType);
        if (!in_array($fulfillmentType, ['SHIP', 'PICKUP'], true)) {
            throw new \RuntimeException('Invalid fulfillment type. Must be SHIP or PICKUP');
        }

        $targetItem = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int) $item->getId() === $itemId) {
                $targetItem = $item;
                break;
            }
        }

        if (!$targetItem) {
            throw new \RuntimeException('Cart item not found');
        }

        $additionalData = $targetItem->getAdditionalData();
        $data = $additionalData ? json_decode($additionalData, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $data['fulfillment_type'] = $fulfillmentType;

        $targetItem->setAdditionalData(json_encode($data));
        $targetItem->save();

        return $quote;
    }

    /**
     * Set shipping address
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param array $addressData Address data
     */
    public function setShippingAddress(\Mage_Sales_Model_Quote $quote, array $addressData): \Mage_Sales_Model_Quote
    {
        $address = $quote->getShippingAddress();
        $address->addData(StoreDefaults::filterAddressKeys($this->sanitizeAddressData($addressData)));

        // Flag to trigger shipping rate collection
        $address->setCollectShippingRates(1);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        return $quote;
    }

    /**
     * Set billing address
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param array $addressData Address data
     * @param bool $sameAsShipping Same as shipping
     */
    public function setBillingAddress(\Mage_Sales_Model_Quote $quote, array $addressData, bool $sameAsShipping = false): \Mage_Sales_Model_Quote
    {
        if ($sameAsShipping) {
            $shippingAddress = $quote->getShippingAddress();
            $addressData = StoreDefaults::extractAddressFields($shippingAddress);
        } else {
            $addressData = $this->sanitizeAddressData($addressData);
        }

        $address = $quote->getBillingAddress();
        $address->addData(StoreDefaults::filterAddressKeys($addressData));
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        return $quote;
    }

    /**
     * Set shipping method
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param string $carrierCode Carrier code
     * @param string $methodCode Method code
     */
    public function setShippingMethod(\Mage_Sales_Model_Quote $quote, string $carrierCode, string $methodCode, bool $skipValidation = false): \Mage_Sales_Model_Quote
    {
        $shippingMethod = $carrierCode . '_' . $methodCode;

        $address = $quote->getShippingAddress();

        // Validate the shipping method is available for this address
        if (!$skipValidation) {
            $mapper = new CartMapper();
            $available = $mapper->getAvailableShippingMethods($address);
            $availableCodes = array_map(
                fn($m) => $m['carrierCode'] . '_' . $m['methodCode'],
                $available,
            );

            if (!in_array($shippingMethod, $availableCodes, true)) {
                throw new \RuntimeException('Shipping method is not available for this address');
            }
        }

        $address->setShippingMethod($shippingMethod);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        return $quote;
    }

    /**
     * Set payment method
     *
     * @param \Mage_Sales_Model_Quote $quote Quote
     * @param string $methodCode Method code
     * @param array|null $additionalData Additional payment data
     */
    public function setPaymentMethod(\Mage_Sales_Model_Quote $quote, string $methodCode, ?array $additionalData = null): \Mage_Sales_Model_Quote
    {
        $paymentData = ['method' => $methodCode];
        if ($additionalData) {
            $paymentData['additional_data'] = $additionalData;
        }

        try {
            $quote->getPayment()->importData($paymentData);
        } catch (\Exception $e) {
            throw new \RuntimeException('Payment method is not available: ' . $e->getMessage());
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        return $quote;
    }

    /**
     * Sanitize address data to prevent stored XSS
     *
     * Strips HTML tags and limits field lengths for all string address fields.
     * Called before passing user-supplied address data to quote address models.
     *
     * @param array $addressData Raw address data from API input
     * @return array Sanitized address data
     */
    private function sanitizeAddressData(array $addressData): array
    {
        $maxLengths = [
            'firstname' => 255,
            'lastname' => 255,
            'company' => 255,
            'city' => 255,
            'region' => 255,
            'postcode' => 20,
            'telephone' => 50,
            'fax' => 50,
            'email' => 255,
            'prefix' => 40,
            'suffix' => 40,
            'middlename' => 255,
            'vat_id' => 50,
        ];

        foreach ($addressData as $key => $value) {
            if ($key === 'street') {
                if (is_array($value)) {
                    $addressData[$key] = array_map(fn($line) => mb_substr(strip_tags((string) $line), 0, 255), $value);
                } elseif (is_string($value)) {
                    $addressData[$key] = mb_substr(strip_tags($value), 0, 255);
                }
                continue;
            }

            if (is_string($value)) {
                $value = strip_tags($value);
                $maxLen = $maxLengths[$key] ?? 255;
                $addressData[$key] = mb_substr($value, 0, $maxLen);
            }
        }

        return $addressData;
    }

    /**
     * Merge guest cart into customer cart
     *
     * @param string $guestMaskedId Guest cart masked ID
     * @param int $customerId Customer ID
     * @return \Mage_Sales_Model_Quote Customer cart with merged items
     */
    public function mergeCarts(string $guestMaskedId, int $customerId): \Mage_Sales_Model_Quote
    {
        $guestCartId = $this->getCartIdFromMaskedId($guestMaskedId);
        // Use loadByIdWithoutStore, admin context may sit on a different store
        // than the guest cart, and store-scoped load() would return an empty
        // quote even though the masked-ID lookup just resolved successfully.
        $guestCart = \Mage::getModel('sales/quote')->loadByIdWithoutStore($guestCartId);

        if (!$guestCart->getId()) {
            throw new \RuntimeException('Guest cart not found');
        }

        // Only genuine guest carts may be merged. Reject a masked ID that
        // resolves to another customer's cart so it cannot be absorbed.
        $sourceCustomerId = $guestCart->getCustomerId();
        if (!$guestCart->getCustomerIsGuest() && $sourceCustomerId && (int) $sourceCustomerId !== $customerId) {
            throw new \RuntimeException('Guest cart not found');
        }

        $customerCart = $this->getCustomerCart($customerId);

        // Merge items from guest cart to customer cart
        $customerCart->merge($guestCart);

        // Import customer default addresses onto the cart so shipping quotes work
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if ($customer->getId()) {
            $defaultShipping = $customer->getDefaultShippingAddress();
            if ($defaultShipping && $defaultShipping->getId()) {
                $shippingAddress = $customerCart->getShippingAddress();
                if (!$shippingAddress->getFirstname()) {
                    $shippingAddress->importCustomerAddress($defaultShipping);
                    $shippingAddress->setSaveInAddressBook(0);
                }
            }
            $defaultBilling = $customer->getDefaultBillingAddress();
            if ($defaultBilling && $defaultBilling->getId()) {
                $billingAddress = $customerCart->getBillingAddress();
                if (!$billingAddress->getFirstname()) {
                    $billingAddress->importCustomerAddress($defaultBilling);
                    $billingAddress->setSaveInAddressBook(0);
                }
            }
        }
        $customerCart->collectTotals();
        $customerCart->save();

        // Deactivate guest cart
        $guestCart->setIsActive(0);
        $guestCart->save();

        return $customerCart;
    }

    /**
     * Generate cryptographically secure masked ID for cart
     *
     * @return string 32-character hex string
     */
    private function generateSecureMaskedId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get cart ID from masked ID via database lookup
     *
     * Security: Only accepts 32-character hex masked IDs (cryptographically secure).
     * Legacy base64 format has been removed as it was predictable and reversible.
     *
     * @param string $maskedId Masked ID (32-char hex string)
     * @return int|null Cart ID or null if not found
     */
    private function getCartIdFromMaskedId(string $maskedId): ?int
    {
        // Only accept secure 32-char hex format
        if (!preg_match('/^[a-f0-9]{32}$/i', $maskedId)) {
            return null;
        }

        // Database lookup
        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $quoteTable = $resource->getTableName('sales/quote');

        $quoteId = $read->fetchOne(
            $read->select()
                ->from($quoteTable, ['entity_id'])
                ->where('masked_quote_id = ?', $maskedId)
                ->where('is_active = ?', 1),
        );

        return $quoteId ? (int) $quoteId : null;
    }




    /**
     * Get super_attribute values for a simple product that belongs to a configurable
     *
     * @param \Mage_Catalog_Model_Product $configurableProduct The parent configurable product
     * @param \Mage_Catalog_Model_Product $simpleProduct The child simple product
     * @return array Attribute ID => Option Value ID mapping
     */
    private function getSuperAttributesForSimple(
        \Mage_Catalog_Model_Product $configurableProduct,
        \Mage_Catalog_Model_Product $simpleProduct,
    ): array {
        $superAttributes = [];

        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $configurableProduct->getTypeInstance(true);

        // Get configurable attributes (size, color, etc.)
        $configurableAttributes = $typeInstance->getConfigurableAttributesAsArray($configurableProduct);

        foreach ($configurableAttributes as $attribute) {
            $attributeId = $attribute['attribute_id'];
            $attributeCode = $attribute['attribute_code'];

            // Get the simple product's value for this attribute
            $optionValue = $simpleProduct->getData($attributeCode);

            if ($optionValue !== null) {
                $superAttributes[$attributeId] = $optionValue;
                $this->logDebug("Super attribute: {$attributeCode} (ID: {$attributeId}) = {$optionValue}");
            }
        }

        return $superAttributes;
    }

    /**
     * Collect quote totals with manual fallback for admin context
     *
     * WORKAROUND: collectTotals() doesn't work properly in admin context.
     * If subtotal is still 0 after collectTotals(), manually calculate from item row totals.
     */
    private function collectAndVerifyTotals(\Mage_Sales_Model_Quote $quote): void
    {
        // Set store context so price calculation uses the correct store (not admin store 0)
        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        // Ensure quote has addresses, collectTotals() calculates per-address,
        // so without addresses all totals (including discounts) return 0
        $quote->getBillingAddress();
        $quote->getShippingAddress();

        // Clear address item cache, getAllItems() caches its result, so if collectTotals
        // was called before the item was added (e.g. during cart creation), the cache is stale.
        foreach ($quote->getAllAddresses() as $address) {
            $address->unsetData('cached_items_all');
            $address->unsetData('cached_items_nominal');
            $address->unsetData('cached_items_nonnominal');
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

    }

    /**
     * Log per-add cart trace lines only when developer mode is on. Without the
     * gate, busy storefronts fill system.log with one line per product add.
     */
    private function logDebug(string $message): void
    {
        if (!\Mage::getIsDeveloperMode()) {
            return;
        }
        \Mage::log($message, \Mage::LOG_DEBUG);
    }
}
