<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

/**
 * Cart Service - Business logic for cart operations
 */
class CartService
{
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
            $defaultStoreId = (int) \Mage::app()->getDefaultStoreView()->getId();
            $quote->setStoreId($defaultStoreId ?: 1);
        }

        if ($customerId) {
            $quote->setCustomerId($customerId);
            $quote->setCustomerIsGuest(0);
        } else {
            $quote->setCustomerIsGuest(1);
        }

        $quote->setIsActive(1);

        // Generate and set masked ID for guest carts (before save to include in same transaction)
        $maskedId = null;
        if (!$customerId) {
            $maskedId = $this->generateSecureMaskedId();
            $quote->setData('masked_quote_id', $maskedId);
        }

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
        if ($qty > 10000) {
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
        if ($qty > 10000) {
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
        $quote->collectTotals();
        $quote->save();

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
        $address->addData($this->sanitizeAddressData($addressData));

        // Flag to trigger shipping rate collection
        $address->setCollectShippingRates(1);

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
            $addressData = $shippingAddress->getData();
        } else {
            $addressData = $this->sanitizeAddressData($addressData);
        }

        $address = $quote->getBillingAddress();
        $address->addData($addressData);
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
            $address->setCollectShippingRates(1);
            $address->collectShippingRates();

            $availableMethods = [];
            foreach ($address->getAllShippingRates() as $rate) {
                $availableMethods[] = $rate->getCarrier() . '_' . $rate->getMethod();
            }

            if (!in_array($shippingMethod, $availableMethods, true)) {
                throw new \RuntimeException('Shipping method is not available for this address');
            }
        }

        $address->setShippingMethod($shippingMethod);
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
    public function setPaymentMethod(\Mage_Sales_Model_Quote $quote, string $methodCode, ?array $additionalData = null, bool $skipValidation = false): \Mage_Sales_Model_Quote
    {
        // Validate the payment method is enabled and available for this quote
        if (!$skipValidation) {
            $store = $quote->getStoreId() ? \Mage::app()->getStore($quote->getStoreId()) : \Mage::app()->getStore();
            $availableMethods = \Mage::helper('payment')->getStoreMethods($store, $quote);

            $isAvailable = false;
            foreach ($availableMethods as $method) {
                if ($method->getCode() === $methodCode) {
                    $isAvailable = true;
                    break;
                }
            }

            if (!$isAvailable) {
                throw new \RuntimeException('Payment method is not available');
            }
        }

        $payment = $quote->getPayment();
        $payment->setMethod($methodCode);

        if ($additionalData) {
            $payment->setAdditionalData(json_encode($additionalData));
        }

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
    public function placeOrder(\Mage_Sales_Model_Quote $quote, string $paymentMethod = 'purchaseorder', ?string $shippingMethod = null): array
    {
        try {
            \Mage::log("PlaceOrder START - Quote ID: {$quote->getId()}, Customer ID: {$quote->getCustomerId()}");

            // Always set/override the payment method
            $quote->getPayment()->setMethod($paymentMethod);
            \Mage::log("PlaceOrder - Payment method set: {$paymentMethod}");

            // Set billing/shipping addresses if not set (for guest checkout)
            if (!$quote->getBillingAddress()->getFirstname()) {
                $quote->getBillingAddress()->setData([
                    'firstname' => 'Walk-in',
                    'lastname' => 'Customer',
                    'street' => 'Store Pickup',
                    'city' => 'Store',
                    'postcode' => '0000',
                    'telephone' => '0000000000',
                    'country_id' => 'AU',
                ]);
            }

            if (!$quote->getShippingAddress()->getFirstname()) {
                $quote->getShippingAddress()->setData([
                    'firstname' => 'Walk-in',
                    'lastname' => 'Customer',
                    'street' => 'Store Pickup',
                    'city' => 'Store',
                    'postcode' => '0000',
                    'telephone' => '0000000000',
                    'country_id' => 'AU',
                ]);
            }

            // Set shipping method if not already set
            if (!$quote->getShippingAddress()->getShippingMethod()) {
                // Use provided shipping method, or default from config, or freeshipping as fallback
                if ($shippingMethod) {
                    $defaultMethod = $shippingMethod;
                } else {
                    $defaultMethod = \Mage::getStoreConfig('maho_pos/general/default_shipping_method', $quote->getStoreId());
                    if (!$defaultMethod) {
                        $defaultMethod = 'freeshipping_freeshipping';
                    }
                }

                $quote->getShippingAddress()->setShippingMethod($defaultMethod);
                // Description will be set by collectTotals based on the method
            }

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
            \Mage::log('PlaceOrder - OrderCreateModel initialized');

            // Import payment data using orderCreateModel to ensure proper availability
            $paymentData = ['method' => $paymentMethod];

            // Add PO number for purchaseorder payment method
            if ($paymentMethod === 'purchaseorder') {
                $paymentData['po_number'] = 'POS-' . date('YmdHis');
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

            $result = [
                'order_id' => (int) $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'grand_total' => (float) $order->getGrandTotal(),
                'invoice' => null,
                'shipment' => null,
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

            return $result;
        } catch (\Exception $e) {
            \Mage::log("Error placing order with payment method '{$paymentMethod}': " . $e->getMessage());
            \Mage::logException($e);
            throw new \RuntimeException('Failed to place order');
        }
    }

    /**
     * Create invoice for order (MDN PointOfSales approach)
     * Uses pay() instead of capture() for offline POS payments
     *
     * @param string $comments Optional invoice comments
     */
    protected function createInvoice(\Mage_Sales_Model_Order $order, string $comments = ''): \Mage_Sales_Model_Order_Invoice
    {
        $convertor = \Mage::getModel('sales/convert_order');
        $invoice = $convertor->toInvoice($order);

        // Browse order items and add to invoice
        foreach ($order->getAllItems() as $orderItem) {
            $invoiceItem = $convertor->itemToInvoiceItem($orderItem);
            $qty = $orderItem->getQtyOrdered();

            // Handle child items with parent
            if ($qty <= 0 && $orderItem->getParentItemId() > 0) {
                $qty = \Mage::getModel('sales/order_item')->load($orderItem->getParentItemId())->getQtyOrdered();
            }

            $invoiceItem->setQty($qty);
            $invoice->addItem($invoiceItem);
        }

        // Add comments if provided
        if ($comments != '') {
            $invoice->addComment($comments, false);
        }

        // Save invoice
        $invoice->collectTotals();
        $invoice->register();

        // Use transaction to save invoice and order together
        $transactionSave = \Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();

        // Mark invoice as paid (offline payment - use pay() not capture())
        $invoice->pay();
        $invoice->save();

        \Mage::log("Invoice created: {$invoice->getIncrementId()} (ID: {$invoice->getId()})");

        return $invoice;
    }

    /**
     * Create shipment for order (MDN PointOfSales approach)
     */
    protected function createShipment(\Mage_Sales_Model_Order $order): \Mage_Sales_Model_Order_Shipment
    {
        $convertor = \Mage::getModel('sales/convert_order');
        $shipment = $convertor->toShipment($order);

        foreach ($order->getAllItems() as $orderItem) {
            // Skip dummy items and items with no qty to ship
            if (!$orderItem->isDummy(true) && !$orderItem->getQtyToShip()) {
                continue;
            }

            // Skip virtual items
            if ($orderItem->getIsVirtual()) {
                continue;
            }

            $shipmentItem = $convertor->itemToShipmentItem($orderItem);

            $qty = $orderItem->getQtyOrdered();

            // Handle child items with parent
            if ($qty == 0 && $orderItem->getParentItemId() > 0) {
                $qty = \Mage::getModel('sales/order_item')->load($orderItem->getParentItemId())->getQtyOrdered();
            }

            $shipmentItem->setQty($qty);
            $shipment->addItem($shipmentItem);
        }

        $shipment->register();

        // Use transaction to save shipment and order together
        $transactionSave = \Mage::getModel('core/resource_transaction')
            ->addObject($shipment)
            ->addObject($shipment->getOrder());
        $transactionSave->save();

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
     * Get catalog rule price for a product and customer group
     * Manually queries catalogrule_product_price table since catalog rules don't auto-apply in admin
     *
     * @param int $productId Product ID
     * @param int $customerGroupId Customer group ID
     * @param int $websiteId Website ID
     * @param int $storeId Store ID (for date context)
     * @return float|null Rule price or null if no rule applies
     */
    private function getCatalogRulePrice(int $productId, int $customerGroupId, int $websiteId, int $storeId): ?float
    {
        try {
            // Get current date in store timezone (returns DateTime)
            $date = \Mage::app()->getLocale()->storeDate($storeId)->format('Y-m-d');

            $resource = \Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_read');
            $tableName = $resource->getTableName('catalogrule/rule_product_price');

            $select = $connection->select()
                ->from($tableName, ['rule_price'])
                ->where('rule_date <= ?', $date)  // Rule has started
                ->where('product_id = ?', $productId)
                ->where('customer_group_id = ?', $customerGroupId)
                ->where('website_id = ?', $websiteId)
                ->where('earliest_end_date IS NULL OR earliest_end_date >= ?', $date)  // Rule hasn't expired
                ->order('rule_date DESC')  // Get most recent rule
                ->order('rule_price ASC')  // If multiple rules on same date, get lowest price
                ->limit(1);

            $rulePrice = $connection->fetchOne($select);

            if ($rulePrice !== false && $rulePrice !== null) {
                \Mage::log("Found catalog rule price {$rulePrice} for product {$productId}, customer group {$customerGroupId}, date {$date}");
                return (float) $rulePrice;
            }

            \Mage::log("No catalog rule price found for product {$productId}, customer group {$customerGroupId}, date {$date} (checked rules <= {$date})");
            return null;
        } catch (\Exception $e) {
            \Mage::log('Error getting catalog rule price: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Re-apply catalog rule prices to all items in a quote
     * Called when customer is assigned to cart to recalculate with customer group pricing
     *
     * @param \Mage_Sales_Model_Quote $quote Quote to update
     */
    public function reapplyCatalogRulePrices(\Mage_Sales_Model_Quote $quote): void
    {
        if (!$quote->getCustomerGroupId()) {
            \Mage::log('No customer group on quote, skipping catalog rule reapplication');
            return;
        }

        \Mage::log("Reapplying catalog rule prices for customer group {$quote->getCustomerGroupId()}");

        // Get website ID from quote's store
        $websiteId = $quote->getWebsiteId();
        if (!$websiteId) {
            $store = \Mage::app()->getStore($quote->getStoreId());
            $websiteId = $store->getWebsiteId();
            \Mage::log("Quote website_id was null, loaded from store: {$websiteId}");
        }

        $hasChanges = false;

        foreach ($quote->getAllVisibleItems() as $item) {
            $productId = $item->getProductId();
            $originalPrice = $item->getPrice();

            // Get base price from product
            $product = \Mage::getModel('catalog/product')->load($productId);
            $basePrice = $product->getPrice();

            // Try to get catalog rule price
            $catalogRulePrice = $this->getCatalogRulePrice(
                $productId,
                $quote->getCustomerGroupId(),
                $websiteId,
                $quote->getStoreId(),
            );

            // Use catalog rule price if it exists and is lower than base price
            $finalPrice = $basePrice;
            if ($catalogRulePrice !== null && $catalogRulePrice < $basePrice) {
                $finalPrice = $catalogRulePrice;
                \Mage::log("Item {$item->getId()}: Applying catalog rule price {$finalPrice} (base: {$basePrice}) for product {$productId}");
            } else {
                \Mage::log("Item {$item->getId()}: No rule price, using base price {$finalPrice} for product {$productId}");
            }

            // Only update if price changed
            if ($finalPrice != $originalPrice) {
                $item->setPrice($finalPrice);
                $item->setBasePrice($finalPrice);
                $item->setCustomPrice($finalPrice);
                $item->setOriginalCustomPrice($finalPrice);
                $item->getProduct()->setIsSuperMode(true);

                // Recalculate row total
                $rowTotal = $finalPrice * $item->getQty();
                $item->setRowTotal($rowTotal);
                $item->setBaseRowTotal($rowTotal);

                $hasChanges = true;
                \Mage::log("Updated item {$item->getId()} price from {$originalPrice} to {$finalPrice}, row_total: {$rowTotal}");
            }
        }

        if ($hasChanges) {
            \Mage::log('Recollecting quote totals after price changes');
            $this->collectAndVerifyTotals($quote);
        }
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
}
