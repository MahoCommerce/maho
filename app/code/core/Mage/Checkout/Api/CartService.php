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

use Mage\Sales\Api\OrderService;
use Maho\ApiPlatform\Service\StoreDefaults;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cart Service - Business logic for cart operations
 */
class CartService
{
    private const MAX_ITEM_QTY = 10000;

    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }
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
            // Use the default store — Mage::app()->getStore() returns admin (0) under Symfony
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

        // Guest cart: allow if accessed by masked ID OR if caller is authenticated
        // (authenticated users get guest carts assigned during login)
        if (!$accessedByMaskedId && $authenticatedCustomerId === null) {
            throw new AccessDeniedHttpException('Guest carts require authentication or masked ID');
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

        \Mage::log("Adding product {$sku} (ID: {$productId}) to quote {$quote->getId()}, quote store_id: {$quote->getStoreId()}");

        // Load product in the quote's store context to get correct prices
        $product = \Mage::getModel('catalog/product')
            ->setStoreId($quote->getStoreId())
            ->load($productId);

        if (!$product->getId()) {
            throw new \RuntimeException("Product with SKU '{$sku}' not found");
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

        // Only auto-detect configurable parent for simple products (not grouped/bundle)
        if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $parentIds = \Mage::getModel('catalog/product_type_configurable')
                ->getParentIdsByChild((int) $productId);

            if (!empty($parentIds)) {
                $parentId = $parentIds[0];
                \Mage::log("Simple product {$productId} has configurable parent {$parentId}, adding as configurable");

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
                        \Mage::log("Using configurable product {$parentId} with super_attributes: " . json_encode($superAttributes));
                    }
                }
            }
        }

        \Mage::log("Product loaded: ID={$product->getId()}, StoreId={$product->getStoreId()}, Price={$product->getPrice()}, FinalPrice={$product->getFinalPrice()}");
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

        // Inject API file uploads into buyRequest (prevents forgery — uses DataObject pattern)
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
        $result = $quote->addProduct($product, $buyRequest);

        // addProduct returns a string error message on failure
        if (is_string($result)) {
            \Mage::log("Failed to add product: {$result}");
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

        // Verify coupon was applied successfully
        if ($quote->getCouponCode() !== $couponCode) {
            throw new \RuntimeException("Coupon code '{$couponCode}' could not be applied");
        }

        return $quote;
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
        $guestCart = \Mage::getModel('sales/quote')->load($guestCartId);

        if (!$guestCart->getId()) {
            throw new \RuntimeException('Guest cart not found');
        }

        $customerCart = $this->getCustomerCart($customerId);

        // Merge items from guest cart to customer cart
        $customerCart->merge($guestCart);
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
     * Place order - Create order, invoice, and shipment for POS
     * Uses adminhtml order creation model for POS-specific features (disabled products, stock overrides, etc.)
     *
     * @param \Mage_Sales_Model_Quote $quote Quote to convert to order
     * @param string $paymentMethod Payment method code
     * @param string|null $shippingMethod Shipping method code (carrier_method format)
     * @return array Order, invoice, and shipment information
     */
    public function placeAdminOrder(\Mage_Sales_Model_Quote $quote, string $paymentMethod = 'purchaseorder', ?string $shippingMethod = null, array $additionalPaymentData = [], ?string $storefrontOrigin = null): array
    {
        try {
            \Mage::log("PlaceOrder START - Quote ID: {$quote->getId()}, Customer ID: {$quote->getCustomerId()}");

            // Apply POS defaults (address, shipping, payment, email)
            $this->preparePosQuote($quote, $shippingMethod, $paymentMethod);
            \Mage::log("PlaceOrder - Payment method set: {$paymentMethod}");

            // Collect totals before order creation
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals()->save();
            \Mage::log('PlaceOrder - Totals collected and quote saved');

            // Use adminhtml order create model (allows disabled products, stock overrides, etc.)
            /** @var \Mage_Adminhtml_Model_Sales_Order_Create $orderCreateModel */
            $orderCreateModel = \Mage::getSingleton('adminhtml/sales_order_create');

            // Get the adminhtml quote session
            /** @var \Mage_Adminhtml_Model_Session_Quote $session */
            $session = \Mage::getSingleton('adminhtml/session_quote');

            // Initialize session with customer and store
            $session->clear();
            $session->setStoreId($quote->getStoreId());

            if ($quote->getCustomerId()) {
                // Registered customer
                $session->setCustomerId($quote->getCustomerId());
                $quote->setCustomerIsGuest(0);
                \Mage::log("PlaceOrder - Registered customer mode, Customer ID: {$quote->getCustomerId()}");

                // Load customer and set addresses from default billing/shipping
                $customer = \Mage::getModel('customer/customer')->load($quote->getCustomerId());
                if ($customer->getId()) {
                    // Set billing address from customer's default
                    $defaultBillingAddress = $customer->getDefaultBillingAddress();
                    if ($defaultBillingAddress && $defaultBillingAddress->getId()) {
                        $billingAddress = $quote->getBillingAddress();
                        $billingAddress->importCustomerAddress($defaultBillingAddress);
                        $billingAddress->setSaveInAddressBook(0);
                        \Mage::log('PlaceOrder - Set billing address from customer default');
                    }

                    // Set shipping address from customer's default
                    $defaultShippingAddress = $customer->getDefaultShippingAddress();
                    if ($defaultShippingAddress && $defaultShippingAddress->getId()) {
                        $shippingAddress = $quote->getShippingAddress();
                        $shippingAddress->importCustomerAddress($defaultShippingAddress);
                        $shippingAddress->setSaveInAddressBook(0);
                        \Mage::log('PlaceOrder - Set shipping address from customer default');
                    }
                }
            } else {
                // Guest checkout
                $session->setCustomerId(-1);
                $quote->setCustomerIsGuest(1);
                \Mage::log('PlaceOrder - Guest checkout mode');
            }

            // Initialize with existing quote
            $orderCreateModel->setQuote($quote);
            $orderCreateModel->getQuote()->setTotalsCollectedFlag(true);
            $orderCreateModel->setRecollect(true);

            // Set account email so admin order create model uses it instead of generating timestamp@example.com
            $customerEmail = $quote->getCustomerEmail();
            if ($customerEmail) {
                $orderCreateModel->setAccountData(['email' => $customerEmail]);
            }

            \Mage::log('PlaceOrder - OrderCreateModel initialized');

            // Import payment data using orderCreateModel to ensure proper availability
            $paymentData = array_merge($additionalPaymentData, ['method' => $paymentMethod]);

            // Add PO number for purchaseorder payment method
            if ($paymentMethod === 'purchaseorder') {
                $paymentData['po_number'] = 'POS-' . \Mage::getModel('core/date')->date('YmdHis');
            }

            $orderCreateModel->getQuote()->getPayment()->addData($paymentData);
            $orderCreateModel->setPaymentData($paymentData);
            \Mage::log("PlaceOrder - Payment method set via orderCreateModel: {$paymentMethod}");

            // Set currency rates (assumes same currency)
            $quote->setStoreToQuoteRate(1);
            $quote->setBaseToGlobalRate(1);
            $quote->setStoreToBaseRate(1);
            $quote->setBaseToQuoteRate(1);

            $orderCreateModel->saveQuote();
            \Mage::log('PlaceOrder - Quote saved via orderCreateModel');

            // Create order via admin model
            \Mage::log('PlaceOrder - About to call createOrder()');
            try {
                $order = $orderCreateModel->createOrder();
            } catch (\Exception $e) {
                // Get validation errors from session if available
                $sessionMessages = $session->getMessages();
                $errorMessages = [];
                if ($sessionMessages) {
                    foreach ($sessionMessages->getItems() as $message) {
                        if ($message->getType() === 'error') {
                            $errorMessages[] = $message->getText();
                        }
                    }
                }

                $errorText = empty($errorMessages) ? $e->getMessage() : implode('; ', $errorMessages);
                \Mage::log('PlaceOrder - createOrder() failed: ' . $errorText);
                throw new \RuntimeException('Order creation failed: ' . $errorText);
            }
            \Mage::log('PlaceOrder - createOrder() returned, checking order...');

            if (!$order || !$order->getId()) {
                \Mage::log('PlaceOrder - FAILED: Order is null or has no ID');
                throw new \RuntimeException('Failed to create order');
            }
            \Mage::log("PlaceOrder - Order created successfully: {$order->getIncrementId()} (ID: {$order->getId()})");

            // Inactivate quote (to avoid it appearing in abandoned cart report)
            $quote->setIsActive(0)->save();

            \Mage::log("Order created: {$order->getIncrementId()} (ID: {$order->getId()})");

            // Generate storefront order token and save origin
            $orderToken = bin2hex(random_bytes(16));
            $resource = \Mage::getSingleton('core/resource');
            $db = $resource->getConnection('core_write');
            $db->update(
                $resource->getTableName('sales/order'),
                [
                    'storefront_order_token' => $orderToken,
                    'storefront_origin' => $storefrontOrigin,
                ],
                ['entity_id = ?' => $order->getId()],
            );

            $result = [
                'order_id' => (int) $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'grand_total' => (float) $order->getGrandTotal(),
                'order_token' => $orderToken,
                'invoice' => null,
                'shipment' => null,
                'redirect_url' => null,
            ];

            // Create invoice (similar to MDN PointOfSales approach)
            if ($order->canInvoice()) {
                $invoice = $this->createInvoice($order);

                $result['invoice'] = [
                    'invoice_id' => (int) $invoice->getId(),
                    'increment_id' => $invoice->getIncrementId(),
                ];
            }

            // Create shipment
            if ($order->canShip()) {
                $shipment = $this->createShipment($order);

                $result['shipment'] = [
                    'shipment_id' => (int) $shipment->getId(),
                    'increment_id' => $shipment->getIncrementId(),
                ];

                \Mage::log('Shipment created - order will auto-transition to complete');
            }

            // Check for redirect-based payment (PayPal, Klarna, hosted checkout, etc.)
            try {
                $redirectUrl = $order->getPayment()->getMethodInstance()->getOrderPlaceRedirectUrl();
                if ($redirectUrl) {
                    // Append order context so the payment controller can load the correct order
                    $separator = str_contains($redirectUrl, '?') ? '&' : '?';
                    $redirectUrl .= $separator . 'order_id=' . (int) $order->getId()
                        . '&sft=' . urlencode($orderToken);
                    $result['redirect_url'] = $redirectUrl;
                }
            } catch (\Exception $e) {
                // Payment method may not support redirect — that's fine
            }

            return $result;
        } catch (\Exception $e) {
            \Mage::log("Error placing order with payment method '{$paymentMethod}': " . $e->getMessage());
            \Mage::logException($e);
            throw new \RuntimeException('Failed to place order: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Place order using standard frontend checkout flow (Mage_Sales_Model_Service_Quote).
     * Used for storefront/API orders where frontend validation rules should apply.
     * Unlike placeOrder() which uses adminhtml order create model (intended for POS/admin),
     * this method validates addresses, shipping, and payment through Mage's standard pipeline.
     */
    public function placeOrder(
        \Mage_Sales_Model_Quote $quote,
        string $paymentMethod,
        array $additionalPaymentData = [],
        ?string $storefrontOrigin = null,
    ): array {
        try {
            \Mage::log("PlaceStorefrontOrder START - Quote ID: {$quote->getId()}, Customer: {$quote->getCustomerId()}");

            // Set checkout method
            if ($quote->getCustomerId()) {
                $quote->setCheckoutMethod('customer');
                $quote->setCustomerIsGuest(0);
            } else {
                $quote->setCheckoutMethod(\Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
                $quote->setCustomerIsGuest(1);
                $quote->setCustomerGroupId(\Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
            }

            // Set payment method with any additional data (e.g. payment intent ID)
            $paymentData = array_merge($additionalPaymentData, ['method' => $paymentMethod]);
            $quote->getPayment()->importData($paymentData);

            // Collect totals
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals()->save();

            // Use the standard frontend quote service — validates addresses, shipping, payment
            /** @var \Mage_Sales_Model_Service_Quote $service */
            $service = \Mage::getModel('sales/service_quote', $quote);
            $order = $service->submitOrder();

            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Failed to create order');
            }

            \Mage::log("PlaceStorefrontOrder - Order created: {$order->getIncrementId()} (ID: {$order->getId()})");

            // Auto-invoice if payment is already captured (e.g. Stripe Elements captures immediately)
            $payment = $order->getPayment();
            if ($payment && $payment->getIsTransactionClosed() && $order->canInvoice()) {
                try {
                    $invoice = $order->prepareInvoice();
                    $invoice->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                    $invoice->register();
                    $invoice->getOrder()->setIsInProcess(true);
                    \Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder())
                        ->save();
                    \Mage::log("PlaceStorefrontOrder - Auto-invoiced: {$order->getIncrementId()}");
                } catch (\Exception $e) {
                    \Mage::logException($e);
                    // Don't fail the order for invoice issues
                }
            }

            // Generate storefront order token and save origin
            $orderToken = bin2hex(random_bytes(16));
            $resource = \Mage::getSingleton('core/resource');
            $db = $resource->getConnection('core_write');
            $db->update(
                $resource->getTableName('sales/order'),
                [
                    'storefront_order_token' => $orderToken,
                    'storefront_origin' => $storefrontOrigin,
                ],
                ['entity_id = ?' => $order->getId()],
            );

            // Send order confirmation email
            try {
                $order->sendNewOrderEmail();
            } catch (\Exception $e) {
                \Mage::logException($e);
                // Don't fail the order for email issues
            }

            $result = [
                'order_id' => (int) $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'grand_total' => (float) $order->getGrandTotal(),
                'order_token' => $orderToken,
                'redirect_url' => null,
            ];

            // Check for redirect-based payment (PayPal, Klarna, hosted checkout, etc.)
            try {
                $redirectUrl = $order->getPayment()->getMethodInstance()->getOrderPlaceRedirectUrl();
                if ($redirectUrl) {
                    $separator = str_contains($redirectUrl, '?') ? '&' : '?';
                    $redirectUrl .= $separator . 'order_id=' . (int) $order->getId()
                        . '&sft=' . urlencode($orderToken);
                    $result['redirect_url'] = $redirectUrl;
                }
            } catch (\Exception $e) {
                // Payment method may not support redirect — that's fine
            }

            return $result;
        } catch (\Exception $e) {
            \Mage::log('Error in placeStorefrontOrder: ' . $e->getMessage());
            \Mage::logException($e);
            throw new \RuntimeException('Failed to place order: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create invoice for order (POS approach)
     * Uses pay() after creation for offline POS payments
     *
     * @param string $comments Optional invoice comments
     */
    protected function createInvoice(\Mage_Sales_Model_Order $order, string $comments = ''): \Mage_Sales_Model_Order_Invoice
    {
        $invoice = $this->orderService->createInvoiceForOrder($order);

        if (!$invoice) {
            throw new \RuntimeException('Order cannot be invoiced');
        }

        if ($comments !== '') {
            $invoice->addComment($comments, false);
        }

        // Mark invoice as paid (offline payment - use pay() not capture())
        $invoice->pay();
        $invoice->save();

        \Mage::log("Invoice created: {$invoice->getIncrementId()} (ID: {$invoice->getId()})");

        return $invoice;
    }

    /**
     * Create shipment for order (POS approach)
     */
    protected function createShipment(\Mage_Sales_Model_Order $order): \Mage_Sales_Model_Order_Shipment
    {
        $shipment = $this->orderService->createShipmentForOrder($order);

        if (!$shipment) {
            throw new \RuntimeException('Order cannot be shipped');
        }

        \Mage::log("Shipment created: {$shipment->getIncrementId()} (ID: {$shipment->getId()})");

        return $shipment;
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
                \Mage::log("Super attribute: {$attributeCode} (ID: {$attributeId}) = {$optionValue}");
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
        // Ensure quote has addresses — collectTotals() calculates per-address,
        // so without addresses all totals (including discounts) return 0
        $quote->getBillingAddress();
        $quote->getShippingAddress();

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
    }

    /**
     * Prepare a quote for POS checkout by applying default address, shipping,
     * payment, and email when not already set.
     */
    public function preparePosQuote(
        \Mage_Sales_Model_Quote $quote,
        ?string $shippingMethod = null,
        ?string $paymentMethod = null,
        ?string $customerEmail = null,
    ): void {
        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        $storeId = $quote->getStoreId() ? (int) $quote->getStoreId() : null;
        $posAddress = StoreDefaults::getPosAddress($storeId);

        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getFirstname()) {
                $shippingAddress->addData($posAddress);
            }
            if (!$shippingAddress->getShippingMethod() || $shippingMethod) {
                $method = $shippingMethod ?: 'freeshipping_freeshipping';
                $shippingAddress->setShippingMethod($method);
                if ($method === 'freeshipping_freeshipping') {
                    $shippingAddress->setShippingDescription('Free Shipping - POS Pickup');
                    $shippingAddress->setShippingAmount(0);
                    $shippingAddress->setBaseShippingAmount(0);
                }
            }
        }

        $billingAddress = $quote->getBillingAddress();
        if (!$billingAddress->getFirstname()) {
            $billingAddress->addData($posAddress);
        }

        $payment = $quote->getPayment();
        if (!$payment->getMethod() || $paymentMethod) {
            $payment->setMethod($paymentMethod ?: 'cashondelivery');
        }

        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail($customerEmail ?: 'pos@store.local');
        }
    }

}
