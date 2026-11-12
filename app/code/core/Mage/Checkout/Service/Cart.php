<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

use Maho\Security\RateLimitScope;

/**
 * Creates and finds carts, changes their items, codes, addresses, shipping and payment, and merges them.
 */
class Mage_Checkout_Service_Cart
{
    /** The address fields that a client can write. */
    private const ADDRESS_FIELDS = [
        'prefix', 'firstname', 'middlename', 'lastname', 'suffix', 'company', 'street', 'city',
        'region', 'region_id', 'postcode', 'country_id',
        'telephone', 'fax', 'email', 'vat_id',
    ];

    /** The shape of a masked cart id, bare of delimiters so a route requirement can reuse it. */
    public const MASKED_ID_PATTERN = '[a-f0-9]{32}';

    /** The additional_information key that holds the payment data of the client that setPaymentMethod() accepted. */
    public const PAYMENT_ADDITIONAL_DATA_KEY = 'api_additional_data';

    /** The coupon codes that one shopper can try in this number of seconds have a limit. */
    public const CODE_RATE_LIMIT_WINDOW = 60;

    /**
     * @phpstan-assert-if-true non-empty-string $maskedId
     */
    public function isValidMaskedId(mixed $maskedId): bool
    {
        return is_string($maskedId) && preg_match('/^' . self::MASKED_ID_PATTERN . '$/i', $maskedId) === 1;
    }

    /**
     * Create an empty active cart in $storeId, for a customer or, when $customerId is null, for a guest.
     * The masked id of the cart is in its masked_quote_id field.
     */
    public function create(?int $customerId, int $storeId): \Mage_Sales_Model_Quote
    {
        // The store drives pricing and the website of gift cards and coupons, so it must be an active store view.
        try {
            $store = \Mage::app()->getStore($storeId);
        } catch (\Throwable) {
            $store = null;
        }
        if (!$store || !$store->getId() || !$store->getIsActive()) {
            throw new \Mage_Core_Exception("Invalid store: {$storeId}");
        }

        $quote = \Mage::getModel('sales/quote');
        $quote->setStoreId((int) $store->getId());

        if ($customerId) {
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new \Mage_Core_Exception("Invalid customer: {$customerId}");
            }
            // setCustomer() also copies the customer group, which prices the cart
            $quote->setCustomer($customer);
            $quote->setCustomerIsGuest(false);
        } else {
            $quote->setCustomerIsGuest();
        }

        $quote->setIsActive();
        $quote->setData('masked_quote_id', $this->generateSecureMaskedId());
        $quote->save();

        return $quote;
    }

    /**
     * Get a cart by its id, in the scope of its own store. Never collects totals: mutations recollect
     * in their service methods, reads collect at the mapping boundary (CartMapper).
     *
     * @throws \Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $cartId): \Mage_Sales_Model_Quote
    {
        return $this->findById($cartId) ?? throw new \Mage_Core_Exception_NoSuchEntity('Cart not found');
    }

    /**
     * Return the cart $cartId as getById() does, or null when there is none, for a caller that has its own answer
     * for that case.
     */
    public function findById(int $cartId): ?\Mage_Sales_Model_Quote
    {
        // loadByIdWithoutStore() also finds a cart of another store than the current one, for example from the admin
        /** @var \Mage_Sales_Model_Quote $quote */
        $quote = \Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);

        if (!$quote->getId()) {
            return null;
        }

        if ($quote->getStoreId()) {
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        return $this->syncCustomerGroup($quote);
    }

    /**
     * Get an active cart by its masked id, or null when no cart has it.
     */
    public function getByMaskedId(string $maskedId): ?\Mage_Sales_Model_Quote
    {
        $cartId = $this->getCartIdFromMaskedId($maskedId);

        return $cartId ? $this->findById($cartId) : null;
    }

    /**
     * Get the active cart of a customer in $storeId, and create it when the customer has none.
     */
    public function getForCustomer(int $customerId, int $storeId): \Mage_Sales_Model_Quote
    {
        // Scope the lookup to the store. loadByCustomer() otherwise
        // returns the most-recently-updated active quote across all stores, which
        // in a multi-store setup surfaces another store's cart (wrong prices,
        // currency and availability). Mirrors AuthTokenProcessor's grant path.
        $loadQuote = fn(): \Mage_Sales_Model_Quote => \Mage::getModel('sales/quote')
            ->setSharedStoreIds([$storeId])
            ->loadByCustomer($customerId);

        $quote = $loadQuote();
        if (!$quote->getId()) {
            // No active cart: create one. Two concurrent first-time requests can
            // race here and each create an active cart; the loser's cart is then
            // dropped on the next load. That's a rare, low-impact outcome (an
            // empty cart), so we don't serialize creation: the cost of a lock on
            // every cart bootstrap isn't worth guarding against it.
            return $this->create($customerId, $storeId);
        }

        return $this->syncCustomerGroup($quote);
    }

    /**
     * Give an active customer cart the current group of its customer.
     *
     * Change the cart in memory only. A read must not save a cart before the access check.
     */
    private function syncCustomerGroup(\Mage_Sales_Model_Quote $quote): \Mage_Sales_Model_Quote
    {
        $customerId = $quote->getCustomerId();
        if (!$customerId || !$quote->getIsActive()) {
            return $quote;
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $groupId = $read->fetchOne(
            $read->select()
                ->from($resource->getTableName('customer/entity'), ['group_id'])
                ->where('entity_id = ?', $customerId),
        );
        if ($groupId === false || (int) $groupId === $quote->getCustomerGroupId()) {
            return $quote;
        }

        return $quote->setCustomer(\Mage::getModel('customer/customer')->load($customerId));
    }

    /**
     * Verify the caller has access to this cart. Denials are 404, not 403:
     * cart ids are enumerable ints, so a status that differs from the
     * missing-cart response would leak which ids exist.
     *
     * @throws \Mage_Core_Exception_NoSuchEntity
     */
    public function verifyAccess(
        \Mage_Sales_Model_Quote $quote,
        bool $accessedByMaskedId,
        ?int $authenticatedCustomerId,
        bool $isPrivileged = false,
    ): void {
        // Staff (an admin token or an API user) can access any cart
        if ($isPrivileged) {
            return;
        }

        $cartCustomerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;

        // Customer-owned cart: verify ownership
        if ($cartCustomerId !== null) {
            if ($authenticatedCustomerId === null || $cartCustomerId !== $authenticatedCustomerId) {
                throw new \Mage_Core_Exception_NoSuchEntity('Cart not found');
            }
            return;
        }

        // Guest cart: only the masked ID grants access. The numeric /carts/{id}
        // path is enumerable, so even authenticated customers must not see
        // someone else's pre-login cart through it.
        if (!$accessedByMaskedId) {
            throw new \Mage_Core_Exception_NoSuchEntity('Cart not found');
        }
    }

    /**
     * Return $quote when the caller can use it. A cart that does not exist gets the same error as a cart of
     * another caller, see verifyAccess().
     *
     * @throws \Mage_Core_Exception_NoSuchEntity
     */
    public function requireAccessible(
        ?\Mage_Sales_Model_Quote $quote,
        bool $accessedByMaskedId,
        ?int $authenticatedCustomerId,
        bool $isPrivileged = false,
    ): \Mage_Sales_Model_Quote {
        if ($quote === null) {
            throw new \Mage_Core_Exception_NoSuchEntity('Cart not found');
        }
        $this->verifyAccess($quote, $accessedByMaskedId, $authenticatedCustomerId, $isPrivileged);

        return $quote;
    }

    /**
     * @throws \Mage_Core_Exception when the product does not exist
     */
    public function getProductById(int $productId, int $storeId): \Mage_Catalog_Model_Product
    {
        $product = \Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        if (!$product->getId()) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('The product could not be found.'));
        }
        return $product;
    }

    /**
     * @throws \Mage_Core_Exception when no product has $sku
     */
    public function getProductBySku(string $sku, int $storeId): \Mage_Catalog_Model_Product
    {
        $productId = $sku === '' ? false : \Mage::getResourceModel('catalog/product')->getIdBySku($sku);
        $product = \Mage::getModel('catalog/product')->setStoreId($storeId);
        if ($productId) {
            $product->load((int) $productId);
        }
        if (!$product->getId()) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__("Product with SKU '%s' not found", $sku));
        }
        return $product;
    }

    /**
     * Add the product that a client asks for to $quote with $buyRequest, and save the cart. A caller that adds many
     * products gives $save false, and collects the totals and saves the cart one time after the last product.
     * getRequestedProduct() has the checks of the product. A buy request without a qty gets the default qty of the
     * product, and a new line gets at least the minimum sale qty. Only staff can set $customPrice.
     *
     * @throws \Mage_Core_Exception_Forbidden when a customer sets a custom price
     * @throws \Mage_Core_Exception_Conflict when the custom price would change the price of units already in the cart
     * @throws \Mage_Core_Exception when the product or the qty is not valid, or the product type refuses the buy
     *                              request. Then the exception has a notice message for an option to fix, or an error
     *                              message for another reason.
     */
    public function addProduct(
        \Mage_Sales_Model_Quote $quote,
        \Mage_Catalog_Model_Product $product,
        \Maho\DataObject $buyRequest,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
        ?float $customPrice = null,
        bool $save = true,
    ): \Mage_Sales_Model_Quote_Item {
        $this->assertCustomPrice($customPrice, $actor);
        $product = $this->getRequestedProduct($quote, $product, $buyRequest, $actor);

        return $this->addToQuote($quote, $product, $buyRequest, $customPrice, $save);
    }

    /**
     * Add $product to $quote with $buyRequest without the checks of getRequestedProduct(), and save the cart unless
     * $save is false. It is for PHP code that selects the product by itself, such as a gift or a fee that the
     * storefront does not show. Never give it a product that a client names: use addProduct() for that.
     *
     * @throws \Mage_Core_Exception when the product type refuses the buy request, see addProduct()
     */
    public function addProductUnchecked(
        \Mage_Sales_Model_Quote $quote,
        \Mage_Catalog_Model_Product $product,
        \Maho\DataObject $buyRequest,
        bool $save = true,
    ): \Mage_Sales_Model_Quote_Item {
        return $this->addToQuote($quote, $product, $buyRequest, null, $save);
    }

    /**
     * Return the product that goes to the cart when a client asks to add $product to $quote. The product must be
     * enabled and in the website of the cart. A simple product that the storefront does not show becomes its
     * configurable parent, see getCartProduct(). A customer can add only a product that the storefront shows.
     * A qty in $buyRequest must be above 0 and not above the maximum sale qty of the product.
     *
     * @throws \Mage_Core_Exception
     */
    public function getRequestedProduct(
        \Mage_Sales_Model_Quote $quote,
        \Mage_Catalog_Model_Product $product,
        \Maho\DataObject $buyRequest,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): \Mage_Catalog_Model_Product {
        $storeId = (int) $quote->getStoreId();
        $sku = (string) $product->getSku();
        $this->assertSellable($product, $storeId, $sku);
        $product = $this->getCartProduct($product, $buyRequest, $storeId);
        $this->assertAvailable($product, $storeId, $actor, $sku);
        $this->prepareOptions($product, $buyRequest);
        if ($buyRequest->hasQty()) {
            $this->assertQty((float) $buyRequest->getQty(), $quote, $product);
        }

        return $product;
    }

    private function addToQuote(
        \Mage_Sales_Model_Quote $quote,
        \Mage_Catalog_Model_Product $product,
        \Maho\DataObject $buyRequest,
        ?float $customPrice,
        bool $save,
    ): \Mage_Sales_Model_Quote_Item {
        return $this->inQuoteStoreScope($quote, function () use ($quote, $product, $buyRequest, $customPrice, $save): \Mage_Sales_Model_Quote_Item {
            \Mage::dispatchEvent('checkout_cart_product_add_before', ['request' => $buyRequest, 'product' => $product]);

            if (!$buyRequest->hasQty()) {
                $buyRequest->setQty(\Mage::helper('catalog/product')->getDefaultQty($product));
            }
            $this->raiseToMinimumSaleQty($quote, $product, $buyRequest);

            // Mage_Checkout_Model_Cart and the wishlist pages read these messages: a notice is an option to fix
            $messages = \Mage::getSingleton('core/message');
            try {
                $item = $quote->addProduct($product, $buyRequest);
            } catch (\Mage_Core_Exception $e) {
                throw $e->addMessage($messages->error($e->getMessage()));
            }
            if (is_string($item)) {
                throw new \Mage_Core_Exception($item)->addMessage($messages->notice($item));
            }

            if ($customPrice !== null) {
                // A persisted id means addProduct() merged into an existing line, and the
                // override would apply to units already in the cart at another price
                // (compared at the column's DECIMAL(12,4) scale, so a re-add of the same value passes)
                $existingOverride = $item->getOriginalCustomPrice();
                if ($item->getId() && ($existingOverride === null || round((float) $existingOverride, 4) !== round($customPrice, 4))) {
                    throw new \Mage_Core_Exception_Conflict(\Mage::helper('checkout')->__('customPrice would reprice units already in the cart; update the existing item instead'));
                }
                $item->setCustomPrice($customPrice);
                $item->setOriginalCustomPrice($customPrice);
            }

            \Mage::dispatchEvent('checkout_cart_product_add_after', ['quote_item' => $item, 'product' => $product]);
            if ($save) {
                $this->refreshShippingRates($quote);
            }

            return $item;
        });
    }

    /**
     * Give the cart line $itemId the options and the qty of $buyRequest, and save the cart.
     *
     * @param array<string, mixed>|\Maho\DataObject|null $params see Mage_Sales_Model_Quote::updateItem()
     * @throws \Mage_Core_Exception_Input when $itemId is empty
     * @throws \Mage_Core_Exception_NoSuchEntity when the cart has no line $itemId
     * @throws \Mage_Core_Exception
     */
    public function reconfigureItem(
        \Mage_Sales_Model_Quote $quote,
        int $itemId,
        \Maho\DataObject $buyRequest,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
        array|\Maho\DataObject|null $params = null,
    ): \Mage_Sales_Model_Quote_Item {
        $storeId = (int) $quote->getStoreId();
        $product = $this->getProductById((int) $this->requireItem($quote, $itemId)->getProduct()->getId(), $storeId);
        $this->assertAvailable($product, $storeId, $actor, (string) $product->getSku());
        $this->prepareOptions($product, $buyRequest);
        if ($buyRequest->hasQty()) {
            $this->assertQty((float) $buyRequest->getQty(), $quote, $product);
        }

        return $this->inQuoteStoreScope($quote, function () use ($quote, $itemId, $product, $buyRequest, $params): \Mage_Sales_Model_Quote_Item {
            $item = $quote->updateItem($itemId, $buyRequest, $params);
            \Mage::dispatchEvent('checkout_cart_product_update_after', ['quote_item' => $item, 'product' => $product]);
            $this->refreshShippingRates($quote);

            return $item;
        });
    }

    /**
     * Give each cart line of $qtyByItemId its new qty, and save the cart once.
     *
     * @param array<int, float> $qtyByItemId
     * @throws \Mage_Core_Exception_Input when an item ID is empty
     * @throws \Mage_Core_Exception_NoSuchEntity when the cart has no such line
     * @throws \Mage_Core_Exception when a qty is not above 0 or is above the maximum sale qty of its product
     */
    public function updateItems(\Mage_Sales_Model_Quote $quote, array $qtyByItemId): \Mage_Sales_Model_Quote
    {
        $items = [];
        foreach ($qtyByItemId as $itemId => $qty) {
            $item = $this->requireItem($quote, (int) $itemId);
            $this->assertQty((float) $qty, $quote, $item->getProduct());
            $items[] = [$item, (float) $qty];
        }

        return $this->inQuoteStoreScope($quote, function () use ($quote, $items): \Mage_Sales_Model_Quote {
            foreach ($items as [$item, $qty]) {
                $item->setQty($qty);
            }
            $this->refreshShippingRates($quote);

            return $quote;
        });
    }

    /**
     * Change the qty and the custom price of the cart line $itemId, and save the cart once. A null value keeps the
     * current one. Only staff can set $customPrice.
     *
     * @throws \Mage_Core_Exception_Forbidden when a customer sets a custom price
     * @throws \Mage_Core_Exception_NoSuchEntity when the cart has no line $itemId
     * @throws \Mage_Core_Exception
     */
    public function updateItem(
        \Mage_Sales_Model_Quote $quote,
        int $itemId,
        ?float $qty,
        ?float $customPrice = null,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Staff,
    ): \Mage_Sales_Model_Quote {
        $this->assertCustomPrice($customPrice, $actor);
        $item = $this->requireItem($quote, $itemId);
        if ($qty === null) {
            $qty = (float) $item->getQty();
        } else {
            $this->assertQty($qty, $quote, $item->getProduct());
        }

        return $this->inQuoteStoreScope($quote, function () use ($quote, $item, $qty, $customPrice): \Mage_Sales_Model_Quote {
            $item->setQty($qty);
            if ($customPrice !== null) {
                $item->setCustomPrice($customPrice);
                $item->setOriginalCustomPrice($customPrice);
            }
            $this->refreshShippingRates($quote);

            return $quote;
        });
    }

    /**
     * Give the cart line $itemId the unit price $price in the currency of the cart, or its catalog price again when
     * $price is null. Only staff can change the price of a line.
     *
     * @throws \Mage_Core_Exception_Forbidden when $actor is a customer
     * @throws \Mage_Core_Exception_NoSuchEntity when the cart has no line $itemId
     * @throws \Mage_Core_Exception when $price is negative
     */
    public function setCustomPrice(
        \Mage_Sales_Model_Quote $quote,
        int $itemId,
        ?float $price,
        \Mage_Checkout_Model_Actor $actor,
    ): \Mage_Sales_Model_Quote {
        $this->assertCustomPrice($price ?? 0.0, $actor);
        $item = $this->requireItem($quote, $itemId);
        $item->setCustomPrice($price);
        $item->setOriginalCustomPrice($price);
        $this->refreshShippingRates($quote);

        return $quote;
    }

    /**
     * @throws \Mage_Core_Exception_Input when $itemId is empty
     * @throws \Mage_Core_Exception_NoSuchEntity when the cart has no line $itemId
     */
    public function removeItem(\Mage_Sales_Model_Quote $quote, int $itemId): \Mage_Sales_Model_Quote
    {
        $this->requireItem($quote, $itemId);

        return $this->inQuoteStoreScope($quote, function () use ($quote, $itemId): \Mage_Sales_Model_Quote {
            $quote->removeItem($itemId);
            $this->refreshShippingRates($quote);

            return $quote;
        });
    }

    public function truncate(\Mage_Sales_Model_Quote $quote): \Mage_Sales_Model_Quote
    {
        $quote->removeAllItems();
        $this->refreshShippingRates($quote);

        return $quote;
    }

    /**
     * Give the shipping address of $quote the country, the region, the postcode and the city of $estimate, and save the
     * cart with the shipping rates of this place.
     *
     * @throws \Mage_Core_Exception_Input when the country does not exist
     */
    public function estimateShipping(\Mage_Sales_Model_Quote $quote, \Mage_Sales_Model_Quote_Address $estimate): \Mage_Sales_Model_Quote
    {
        $countryId = (string) $estimate->getCountryId();
        try {
            $country = \Mage::getModel('directory/country')->loadByCode($countryId);
        } catch (\Mage_Core_Exception) {
            $country = null;
        }
        if (!$country?->getId()) {
            throw new \Mage_Core_Exception_Input()->addError('country_id', \Mage::helper('directory')->__('Invalid country code: %s', $countryId));
        }

        $quote->getShippingAddress()
            ->setCountryId($countryId)
            ->setCity($estimate->getCity())
            ->setPostcode($estimate->getPostcode())
            ->setRegionId($estimate->getRegionId())
            ->setRegion($estimate->getRegion());
        $this->refreshShippingRates($quote);

        return $quote;
    }

    /**
     * Add item to cart
     *
     * @param array $options Custom options (option_id => value), or the keys of a buy request
     * @param float|null $customPrice the unit price in the currency of the cart, which only staff can set
     */
    #[\Deprecated(message: 'use getProductBySku() and addProduct()', since: '27.1')]
    public function addItem(
        \Mage_Sales_Model_Quote $quote,
        string $sku,
        float $qty,
        array $options = [],
        ?float $customPrice = null,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): \Mage_Sales_Model_Quote {
        $buyRequest = new \Maho\DataObject(['qty' => $qty]);
        $structuredKeys = [
            'options', 'options_files', 'links', 'super_group', 'bundle_option', 'bundle_option_qty',
            'giftcard_amount', 'giftcard_sender_name', 'giftcard_sender_email', 'giftcard_recipient_name',
            'giftcard_recipient_email', 'giftcard_message', 'giftcard_delivery_date',
        ];
        if (array_intersect_key($options, array_flip(['options', 'links', 'super_group', 'bundle_option', 'giftcard_amount', 'options_files']))) {
            $buyRequest->addData(array_filter(array_intersect_key($options, array_flip($structuredKeys))));
        } elseif ($options !== []) {
            $buyRequest->setData('options', $options);
        }

        $product = $this->getProductBySku($sku, (int) $quote->getStoreId());
        $this->addProduct($quote, $product, $buyRequest, $actor, $customPrice);

        return $quote;
    }

    /**
     * @throws \Mage_Core_Exception_Input when $itemId is empty
     * @throws \Mage_Core_Exception_NoSuchEntity when the cart has no line $itemId
     */
    private function requireItem(\Mage_Sales_Model_Quote $quote, int $itemId): \Mage_Sales_Model_Quote_Item
    {
        $helper = \Mage::helper('checkout');
        if ($itemId <= 0) {
            throw new \Mage_Core_Exception_Input()->addError('item_id', $helper->__('Item ID is required'));
        }
        $item = $quote->getItemById($itemId);
        if (!$item) {
            throw new \Mage_Core_Exception_NoSuchEntity($helper->__("Cart item with ID '%s' not found", $itemId));
        }
        return $item;
    }

    /**
     * The stock check of a cart line marks a qty above the maximum sale qty as an error of the line, and does not
     * run for a cart in super mode (the admin order create). Refuse such a qty before it goes to the cart.
     *
     * @throws \Mage_Core_Exception when $qty is not above 0 or is above the maximum sale qty of $product
     */
    private function assertQty(float $qty, \Mage_Sales_Model_Quote $quote, \Mage_Catalog_Model_Product $product): void
    {
        if ($qty <= 0) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Quantity must be greater than zero'));
        }
        $stockItem = $quote->getIsSuperMode() ? null : $product->getStockItem();
        $maximumQty = $stockItem instanceof \Mage_CatalogInventory_Model_Stock_Item ? $stockItem->getMaxSaleQty() : 0.0;
        if ($maximumQty > 0 && $qty > $maximumQty) {
            throw new \Mage_Core_Exception(
                \Mage::helper('cataloginventory')->__('The maximum quantity allowed for purchase is %s.', $maximumQty * 1),
            );
        }
    }

    /**
     * @throws \Mage_Core_Exception_Forbidden when a customer sets a price
     * @throws \Mage_Core_Exception when $price is negative
     */
    private function assertCustomPrice(?float $price, \Mage_Checkout_Model_Actor $actor): void
    {
        if ($price === null) {
            return;
        }
        if ($actor !== \Mage_Checkout_Model_Actor::Staff) {
            throw new \Mage_Core_Exception_Forbidden(\Mage::helper('checkout')->__('customPrice requires admin or carts/write authorization'));
        }
        if ($price < 0) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Custom price cannot be negative'));
        }
    }

    /**
     * @throws \Mage_Core_Exception when $product is disabled or is not in the website of $storeId
     */
    private function assertSellable(\Mage_Catalog_Model_Product $product, int $storeId, string $sku): void
    {
        $websiteId = (int) \Mage::app()->getStore($storeId)->getWebsiteId();
        if ((int) $product->getStatus() !== \Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            || !in_array($websiteId, array_map(intval(...), (array) $product->getWebsiteIds()), true)
        ) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__("Product '%s' is not available", $sku));
        }
    }

    /**
     * Staff can add any product of the website that is enabled. A customer can add only a product that the
     * storefront shows.
     *
     * @throws \Mage_Core_Exception
     */
    private function assertAvailable(\Mage_Catalog_Model_Product $product, int $storeId, \Mage_Checkout_Model_Actor $actor, string $sku): void
    {
        $this->assertSellable($product, $storeId, $sku);
        if ($actor === \Mage_Checkout_Model_Actor::Customer && !\Mage::helper('catalog/product')->isVisibleInStore($product, $storeId)) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__("Product '%s' is not available", $sku));
        }
    }

    /**
     * Return the product that goes to the cart for $product: the configurable parent when $product is a simple product
     * that the storefront does not show, else $product. For a parent, point $buyRequest at it with the values of
     * $product. A caller that adds a product without addProduct() uses it, so that a variant goes to the cart as its
     * parent on every path.
     *
     * @throws \Mage_Core_Exception when the parent is disabled or is not in the website of $storeId
     */
    public function getCartProduct(
        \Mage_Catalog_Model_Product $product,
        \Maho\DataObject $buyRequest,
        int $storeId,
    ): \Mage_Catalog_Model_Product {
        if ($product->getTypeId() !== \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
            || (int) $product->getVisibility() !== \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
        ) {
            return $product;
        }

        $parentId = \Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild((int) $product->getId())[0] ?? null;
        if (!$parentId) {
            return $product;
        }
        $parent = \Mage::getModel('catalog/product')->setStoreId($storeId)->load($parentId);
        if (!$parent->getId()) {
            return $product;
        }
        $superAttributes = $this->getSuperAttributesForSimple($parent, $product);
        if ($superAttributes === []) {
            return $product;
        }
        $this->assertSellable($parent, $storeId, (string) $product->getSku());

        $buyRequest->setData('product', (int) $parent->getId());
        $buyRequest->setData('super_attribute', $superAttributes);
        $this->logDebug("Simple product {$product->getId()} goes to the cart as its configurable parent {$parent->getId()}");

        return $parent;
    }

    /**
     * Check the uploaded files of the buy request, and give a date, date and time, or time option that the buy request
     * holds as one string the array shape of the product form.
     *
     * @throws \Mage_Core_Exception when a file is not for a file option of $product, or has no name or no data
     */
    private function prepareOptions(\Mage_Catalog_Model_Product $product, \Maho\DataObject $buyRequest): void
    {
        $files = $buyRequest->getData('options_files');
        if (!empty($files)) {
            $optionsFiles = [];
            foreach ((array) $files as $optionId => $fileData) {
                $optionId = (int) $optionId;
                $productOption = $product->getOptionById((string) $optionId);
                if (!$productOption || $productOption->getType() !== \Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE) {
                    throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Option ID %s is not a valid file-type option for this product', $optionId));
                }
                if (!is_array($fileData) || empty($fileData['base64_encoded_data']) || empty($fileData['name'])) {
                    throw new \Mage_Core_Exception(\Mage::helper('checkout')->__("File option %s requires 'name' and 'base64_encoded_data'", $optionId));
                }
                $optionsFiles[$optionId] = $fileData;
            }
            $buyRequest->setData('options_files', $optionsFiles);
        }

        $options = $buyRequest->getData('options');
        if (!is_array($options) || $options === []) {
            return;
        }
        $shapes = [
            \Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE => 'date',
            \Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME => 'datetime',
            \Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME => 'time',
        ];
        foreach ($product->getOptions() as $option) {
            $optionId = (string) $option->getId();
            if (is_string($options[$optionId] ?? null) && isset($shapes[$option->getType()])) {
                $options[$optionId] = [$shapes[$option->getType()] => $options[$optionId]];
            }
        }
        $buyRequest->setData('options', $options);
    }

    /**
     * A new line below the minimum sale qty of its product gets the minimum. The stock check does not run for a cart
     * in super mode (the admin order create), so its qty stays.
     */
    private function raiseToMinimumSaleQty(\Mage_Sales_Model_Quote $quote, \Mage_Catalog_Model_Product $product, \Maho\DataObject $buyRequest): void
    {
        $stockItem = $product->getStockItem();
        if ($quote->getIsSuperMode() || $product->isConfigurable() || !$stockItem) {
            return;
        }
        $minimumQty = (float) $stockItem->getMinSaleQty();
        if ($minimumQty > 0 && (float) $buyRequest->getQty() < $minimumQty && !$quote->hasProductId($product->getId())) {
            $buyRequest->setQty($minimumQty);
        }
    }

    /**
     * Collect the shipping rates of $quote again and save the cart. A change of the items can change the rates, and
     * a later choice of a shipping method is checked against the saved rates.
     */
    public function refreshShippingRates(\Mage_Sales_Model_Quote $quote): \Mage_Sales_Model_Quote
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Apply the coupon $couponCode to $quote. A coupon that applies tells that its code exists, so a shopper
     * has a limit of codes per minute. Staff has no limit.
     * When the coupon does not apply, the cart keeps its previous coupon and nothing is saved.
     *
     * @throws \Mage_Core_Exception_Input when the code is empty
     * @throws \Mage_Core_Exception_Conflict when the cart has no items
     * @throws \Mage_Core_Exception_TooManyRequests when the shopper is over the limit
     * @throws \Mage_Core_Exception when the code does not exist or its rule does not apply to the cart
     */
    public function applyCoupon(
        \Mage_Sales_Model_Quote $quote,
        string $couponCode,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): \Mage_Sales_Model_Quote {
        $helper = \Mage::helper('checkout');
        $couponCode = trim($couponCode);
        if ($couponCode === '') {
            throw new \Mage_Core_Exception_Input()->addError('coupon_code', \Mage::helper('salesrule')->__('Coupon code is required'));
        }
        if (!$quote->getItemsCount()) {
            throw new \Mage_Core_Exception_Conflict($helper->__('Your shopping cart is empty.'));
        }
        if ($actor === \Mage_Checkout_Model_Actor::Customer) {
            \Mage::helper('core')->rateLimiter(
                'cart_coupon',
                (int) \Mage::getStoreConfig(\Mage_SalesRule_Service_Rule::XML_PATH_CHECK_RATE_LIMIT),
                self::CODE_RATE_LIMIT_WINDOW,
                RateLimitScope::Ip,
            )->attemptOrThrow();
        }

        // A code that is longer than a coupon code can be does not exist
        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        if (strlen($couponCode) <= \Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH) {
            $coupon->load($couponCode, 'code');
        }
        if (!$coupon->getId()) {
            throw new \Mage_Core_Exception($helper->__("Coupon code '%s' is not valid", $couponCode));
        }

        $previousCode = (string) $quote->getCouponCode();

        return $this->inQuoteStoreScope($quote, function () use ($quote, $couponCode, $coupon, $previousCode, $helper): \Mage_Sales_Model_Quote {
            // A coupon can give free shipping, so the rates must follow it
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setCouponCode($couponCode);
            self::collectTotalsInCurrentScope($quote);

            // setCouponCode() keeps the string even when the rule does not fire
            // (inactive/expired/exhausted/wrong website), so confirm the rule id
            // landed on a quote address before persisting anything
            if ($quote->getCouponCode() !== $couponCode || !$this->isCouponRuleApplied($quote, (int) $coupon->getRuleId())) {
                // The caller can still show or save this cart, so give it back its coupon and its totals
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->setCouponCode($previousCode);
                self::collectTotalsInCurrentScope($quote);
                throw new \Mage_Core_Exception($helper->__("Coupon code '%s' could not be applied", $couponCode));
            }

            $quote->save();

            return $quote;
        });
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
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode('');
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Apply $code, the input of the one code box, to $quote: as a coupon when it is one, else as a gift card of the
     * website of the cart. Return the type that applied.
     *
     * @return 'coupon'|'giftcard'
     * @throws \Mage_Core_Exception_TooManyRequests when the shopper is over the limit
     * @throws \Mage_Core_Exception
     */
    public function applyCode(
        \Mage_Sales_Model_Quote $quote,
        string $code,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): string {
        $code = trim($code);
        try {
            $this->applyCoupon($quote, $code, $actor);
            return 'coupon';
        } catch (\Mage_Core_Exception_Input|\Mage_Core_Exception_Conflict|\Mage_Core_Exception_TooManyRequests $e) {
            throw $e;
        } catch (\Mage_Core_Exception) {
            // The code is not a coupon of this cart, so it can be a gift card
        }

        if (\Mage::helper('core')->isModuleEnabled('Maho_Giftcard')) {
            try {
                \Mage::getService('giftcard/giftcard')->getByCode($code, (int) $quote->getStore()->getWebsiteId());
            } catch (\Mage_Core_Exception_NoSuchEntity) {
                throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Promo code "%s" is not valid.', $code));
            }
            \Mage::getService('giftcard/giftcard')->applyToCart($quote, $code, null, $actor);
            return 'giftcard';
        }
        throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Promo code "%s" is not valid.', $code));
    }

    /**
     * Set (or update) the gift message on the whole cart, or on a single cart
     * item when $itemId is given. Mirrors the storefront
     * Mage_GiftMessage_Model_Observer::checkoutEventCreateGiftMessage flow:
     * a giftmessage/message row is created/loaded and the entity's
     * gift_message_id is pointed at it. Requires the GiftMessage module and the
     * relevant store-config toggle to be enabled.
     *
     * @throws \Mage_Core_Exception when gift messages are disabled for the target
     */
    public function setGiftMessage(
        \Mage_Sales_Model_Quote $quote,
        ?int $itemId,
        string $sender,
        string $recipient,
        string $message,
    ): \Mage_Sales_Model_Quote {
        if (!\Mage::helper('core')->isModuleEnabled('Mage_GiftMessage')) {
            throw new \Mage_Core_Exception('Gift messages are not available');
        }

        $entity = $this->resolveGiftMessageEntity($quote, $itemId);
        $helper = \Mage::helper('giftmessage/message');
        $type = $itemId === null ? 'quote' : 'item';
        if (!$helper->isMessagesAvailable($type, $entity, $quote->getStoreId())) {
            throw new \Mage_Core_Exception('Gift messages are not available for this ' . ($itemId === null ? 'cart' : 'item'));
        }

        if (trim($message) === '') {
            return $this->removeGiftMessage($quote, $itemId);
        }

        $giftMessage = \Mage::getModel('giftmessage/message');
        if ($entity->getGiftMessageId()) {
            $giftMessage->load($entity->getGiftMessageId());
        }
        $giftMessage->setSender(trim($sender))
            ->setRecipient(trim($recipient))
            ->setMessage($message)
            ->save();

        $entity->setGiftMessageId($giftMessage->getId())->save();

        return $quote;
    }

    /**
     * Remove the gift message from the cart or a single cart item.
     */
    public function removeGiftMessage(\Mage_Sales_Model_Quote $quote, ?int $itemId): \Mage_Sales_Model_Quote
    {
        $entity = $this->resolveGiftMessageEntity($quote, $itemId);

        $messageId = (int) $entity->getGiftMessageId();
        if ($messageId) {
            $giftMessage = \Mage::getModel('giftmessage/message')->load($messageId);
            if ($giftMessage->getId()) {
                $giftMessage->delete();
            }
            $entity->setGiftMessageId(0)->save();
        }

        return $quote;
    }

    /**
     * Resolve the entity a gift message attaches to: the quote itself, or one of
     * its items. Throws when the item id doesn't belong to the quote.
     */
    private function resolveGiftMessageEntity(\Mage_Sales_Model_Quote $quote, ?int $itemId): \Mage_Core_Model_Abstract
    {
        if ($itemId === null) {
            return $quote;
        }
        $item = $quote->getItemById($itemId);
        if (!$item || !$item->getId()) {
            throw new \Mage_Core_Exception_NoSuchEntity('Cart item not found');
        }
        return $item;
    }

    /**
     * Check $address with the rules of the address form of $actor, then with the rules of the address model. Staff
     * has the address form of the admin, with the fields that the storefront does not show. A customer has the
     * address form of the storefront. An address that a flow marks with should_ignore_validation passes. A field that
     * the condition of its attribute hides loses its value first.
     *
     * @throws \Mage_Core_Exception_Input one error for each problem
     */
    public function validateAddress(
        \Mage_Sales_Model_Quote_Address $address,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): void {
        if ($address->getShouldIgnoreValidation()) {
            return;
        }

        $isStaff = $actor === \Mage_Checkout_Model_Actor::Staff;
        $errors = new \Mage_Core_Exception_Input();
        /** @var \Mage_Customer_Model_Form $form */
        $form = \Mage::getModel('customer/form')
            ->setFormCode($isStaff ? 'adminhtml_customer_address' : 'customer_address_edit')
            ->setEntityType('customer_address')
            ->setEntity($address);
        \Mage::helper('eav')->clearHiddenValues($address, $form->getAttributes());
        $data = ['street' => $address->getStreet()] + $address->getData();
        foreach ($form->getAttributes() as $code => $attribute) {
            if (!$isStaff && !$attribute->getIsVisible()) {
                continue;
            }
            $result = \Mage_Eav_Model_Attribute_Data::factory($attribute, $address)
                ->setExtractedData($data)
                ->validateValue($data[$code] ?? null);
            $errors->addValidationResult(is_array($result) ? array_values($result) : $result, (string) $code);
        }
        $errors->throwIfErrors();

        $result = $address->validate();
        $errors->addValidationResult(is_array($result) ? array_values($result) : $result, 'address');
        $errors->throwIfErrors();
    }

    /**
     * Copy the saved address $customerAddressId of the customer of $quote into the $addressType address of $quote,
     * and save the cart.
     *
     * @throws \Mage_Core_Exception when the saved address is not an address of the customer of $quote
     * @throws \Mage_Core_Exception_Input when the saved address is not valid
     */
    public function useCustomerAddress(\Mage_Sales_Model_Quote $quote, string $addressType, int $customerAddressId): \Mage_Sales_Model_Quote
    {
        $customerAddress = \Mage::getModel('customer/address')->load($customerAddressId);
        if (!$customerAddress->getId() || !$quote->getCustomerId()
            || (int) $customerAddress->getCustomerId() !== (int) $quote->getCustomerId()
        ) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Customer Address is not valid.'));
        }
        $address = $addressType === \Mage_Sales_Model_Quote_Address::TYPE_BILLING
            ? $quote->getBillingAddress()
            : $quote->getShippingAddress()->setCollectShippingRates(true);
        $address->importCustomerAddress($customerAddress)->setSaveInAddressBook(false);

        return $this->inQuoteStoreScope($quote, function () use ($quote, $address): \Mage_Sales_Model_Quote {
            $this->validateAddress($address);
            $this->collectAndSave($quote);

            return $quote;
        });
    }

    /**
     * Give the shipping address of $quote the fields of the billing address, mark it as the same as the billing
     * address, and save the cart. The shipping method stays.
     */
    public function useBillingForShipping(\Mage_Sales_Model_Quote $quote): \Mage_Sales_Model_Quote
    {
        $billing = $quote->getBillingAddress();
        $shipping = $quote->getShippingAddress();
        foreach ($this->getBillingCopyFields() as $field) {
            $shipping->setData($field, $billing->getData($field));
        }
        $shipping->setSameAsBilling(true)
            ->setSaveInAddressBook(false)
            ->setCollectShippingRates(true);
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Return true when the shipping address of $quote is marked as the same as the billing address, and has each
     * field that useBillingForShipping() copies.
     */
    public function isShippingSameAsBilling(\Mage_Sales_Model_Quote $quote): bool
    {
        $billing = $quote->getBillingAddress();
        $shipping = $quote->getShippingAddress();
        if (!$shipping->getSameAsBilling()) {
            return false;
        }
        return array_all($this->getBillingCopyFields(), fn(string $field): bool => $shipping->getData($field) == $billing->getData($field));
    }

    /**
     * @return list<string> the fields of the billing address that useBillingForShipping() copies. The VAT check of
     *                      the customer group can read the result of the VAT number check on the shipping address.
     */
    private function getBillingCopyFields(): array
    {
        $eavConfig = \Mage::getSingleton('eav/config');

        return [
            ...$eavConfig->getEntityAttributeCodes($eavConfig->getEntityType('customer_address')),
            'customer_address_id', 'email', 'vat_is_valid', 'vat_request_id', 'vat_request_date', 'vat_request_success',
        ];
    }

    /**
     * Give the shipping address of $quote the shipping method $code ("carrier_method"), and save the cart.
     *
     * @throws \Mage_Core_Exception_Input when $code is empty
     * @throws \Mage_Core_Exception when the address has no rate for $code, or the rate has an error
     */
    public function selectShippingMethod(\Mage_Sales_Model_Quote $quote, string $code): \Mage_Sales_Model_Quote
    {
        if ($code === '') {
            throw new \Mage_Core_Exception_Input()->addError('shipping_method', \Mage::helper('checkout')->__('Invalid shipping method.'));
        }

        return $this->inQuoteStoreScope($quote, function () use ($quote, $code): \Mage_Sales_Model_Quote {
            $address = $quote->getShippingAddress();
            $address->collectShippingRates();
            $this->assertShippingMethodAvailable($quote, $code);
            $address->setShippingMethod($code);
            $this->collectAndSave($quote);

            return $quote;
        });
    }

    /**
     * Return the checks of a payment method for $actor. Staff enters an order in the back office, with the
     * methods for internal use. A customer has the methods of the checkout.
     */
    public function getPaymentChecks(\Mage_Checkout_Model_Actor $actor): int
    {
        return $actor === \Mage_Checkout_Model_Actor::Staff
            ? \Mage_Payment_Model_Method_Abstract::CHECKS_INTERNAL
            : \Mage_Payment_Model_Method_Abstract::CHECKS_CHECKOUT;
    }

    /**
     * Give $quote the payment method and the data of $paymentData, and save the cart. The method must be available
     * for the cart and must accept the data. $actor gives the checks of the method, see getPaymentChecks(): the
     * client cannot choose them.
     *
     * @param array<string, mixed> $paymentData the code of the method in "method", and the fields of its form
     * @throws \Mage_Core_Exception
     */
    public function selectPaymentMethod(
        \Mage_Sales_Model_Quote $quote,
        array $paymentData,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): \Mage_Sales_Model_Quote {
        $paymentData['checks'] = $this->getPaymentChecks($actor);
        $import = fn() => $this->importPayment($quote, $paymentData);
        // Staff imports in the scope of the caller, in which Mage_Sales_Service_Order::submit() also places the order
        $actor === \Mage_Checkout_Model_Actor::Staff ? $import() : $this->inQuoteStoreScope($quote, $import);
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Give $quote the checkout data that a client sends with its order, in one pass: the addresses, the shipping
     * method, and the payment method with its data. A null value keeps what the cart has. The totals are collected
     * for the addresses and the shipping method, then again for the payment method, and the cart is saved once at
     * the end. When a step fails, nothing is saved.
     *
     * @param array<string, mixed>|null $shippingAddress the fields of the address model, see applyShippingAddress()
     * @param array<string, mixed>|null $billingAddress the fields of the address model, see applyBillingAddress()
     * @param string|null $shippingMethod "carrier_method"
     * @param array<string, mixed>|null $paymentData see setPaymentMethod()
     * @throws \Mage_Core_Exception_Input when an address is not valid. The name of each field starts with
     *                                    "shipping_address." or "billing_address."
     * @throws \Mage_Core_Exception when the shipping method or the payment method is not available for the cart
     */
    public function applyCheckoutState(
        \Mage_Sales_Model_Quote $quote,
        ?array $shippingAddress = null,
        ?array $billingAddress = null,
        ?string $shippingMethod = null,
        ?string $paymentMethod = null,
        ?array $paymentData = null,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): \Mage_Sales_Model_Quote {
        $addresses = ['shipping_address' => $shippingAddress, 'billing_address' => $billingAddress];
        foreach ($addresses as $prefix => $addressData) {
            if ($addressData === null) {
                continue;
            }
            try {
                $prefix === 'shipping_address'
                    ? $this->applyShippingAddress($quote, $addressData, $actor)
                    : $this->applyBillingAddress($quote, $addressData, $actor);
            } catch (\Mage_Core_Exception_Input $e) {
                $fields = array_unique(array_column($e->getErrors(), 'field'));
                throw $e->withFieldNames(array_combine($fields, array_map(fn(string $field): string => "{$prefix}.{$field}", $fields)));
            }
        }

        $hasShippingMethod = (string) $shippingMethod !== '' && !$quote->isVirtual();
        if ($hasShippingMethod) {
            $quote->getShippingAddress()->setShippingMethod($shippingMethod)->setCollectShippingRates(true);
        }
        // One collection gives the prices of the addresses and of the shipping method, and the totals that the
        // payment method checks
        $this->collectAndVerifyTotals($quote);
        if ($hasShippingMethod) {
            // A method that the client made up has no rate, and must not go to the saved cart
            $this->assertShippingMethodAvailable($quote, (string) $shippingMethod);
        }

        if ((string) $paymentMethod !== '') {
            $this->assertPaymentMethodAvailable($quote, (string) $paymentMethod);
            $this->importPaymentData($quote, (string) $paymentMethod, $paymentData, $this->getPaymentChecks($actor));
            // The payment method can add a total, such as a payment fee
            $this->collectAndVerifyTotals($quote);
        }
        $this->inQuoteStoreScope($quote, fn() => $quote->save());

        return $quote;
    }

    /**
     * @param array<string, mixed> $paymentData
     * @throws \Mage_Core_Exception
     */
    private function importPayment(\Mage_Sales_Model_Quote $quote, array $paymentData): void
    {
        $methodCode = (string) ($paymentData['method'] ?? '');
        if (empty(\Mage::helper('payment')->getPaymentMethods($quote->getStoreId())[$methodCode]['model'])) {
            throw new \Mage_Core_Exception(\Mage::helper('sales')->__('The requested Payment Method is not available.'));
        }
        if ($quote->isVirtual()) {
            $quote->getBillingAddress()->setPaymentMethod($methodCode);
        } else {
            // A payment method can change the shipping rates
            $quote->getShippingAddress()->setPaymentMethod($methodCode)->setCollectShippingRates(true);
        }
        $quote->getPayment()->importData($paymentData);
    }

    /**
     * Give the shipping address of $quote the fields of $addressData and check it, without a save, for callers that
     * batch several checkout fields and collect once afterwards, as applyCheckoutState() does. $actor gives the address
     * form of the check, see validateAddress(). The save_in_address_book field
     * applies to a customer cart only. The address is no longer the same as the billing address, as on the shipping
     * step of the checkout, so that the order adds it to the address book on its own.
     *
     * @throws \Mage_Core_Exception_Input when the address is not valid
     */
    public function applyShippingAddress(
        \Mage_Sales_Model_Quote $quote,
        array $addressData,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): void {
        $address = $quote->getShippingAddress();
        $address->addData(array_intersect_key($addressData, array_flip(self::ADDRESS_FIELDS)))->setSameAsBilling(false);
        $this->applySaveInAddressBook($quote, $address, $addressData);
        $this->inQuoteStoreScope($quote, fn() => $this->validateAddress($address, $actor));

        // Flag to trigger shipping rate collection
        $address->setCollectShippingRates();
        // Address changes reprice tax and shipping; a stale flag would let the
        // read boundary (CartMapper) skip recollecting them
        $quote->setTotalsCollectedFlag(false);
    }

    /**
     * Give the billing address of $quote the fields of $addressData and check it, without a save. The
     * save_in_address_book field applies to a customer cart only.
     *
     * @throws \Mage_Core_Exception_Input when the address is not valid
     */
    public function applyBillingAddress(
        \Mage_Sales_Model_Quote $quote,
        array $addressData,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): void {
        $address = $quote->getBillingAddress();
        $address->addData(array_intersect_key($addressData, array_flip(self::ADDRESS_FIELDS)));
        $this->applySaveInAddressBook($quote, $address, $addressData);
        $this->inQuoteStoreScope($quote, fn() => $this->validateAddress($address, $actor));
        $quote->setTotalsCollectedFlag(false);
    }

    /**
     * Mark $address for the address book of the customer when $addressData asks for it, as the Save in Address Book
     * checkbox of the checkout and of the admin order create does. Mage_Sales_Service_Order::submit() adds it.
     */
    private function applySaveInAddressBook(\Mage_Sales_Model_Quote $quote, \Mage_Sales_Model_Quote_Address $address, array $addressData): void
    {
        $address->setSaveInAddressBook($quote->getCustomerId() && !empty($addressData['save_in_address_book']));
    }

    /**
     * @throws \Mage_Core_Exception_Input when the address is not valid
     */
    public function setShippingAddress(\Mage_Sales_Model_Quote $quote, array $addressData): \Mage_Sales_Model_Quote
    {
        $this->applyShippingAddress($quote, $addressData);
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Give the billing address of $quote the fields of $addressData, or the fields of the shipping address when
     * $sameAsShipping is true, and save the cart. With $sameAsShipping, only save_in_address_book of $addressData
     * applies, and the shipping address becomes the same as the billing address, so that the order adds the address
     * to the address book once.
     *
     * @throws \Mage_Core_Exception_Conflict when $sameAsShipping is true and the cart has no shipping address
     * @throws \Mage_Core_Exception_Input when the address is not valid
     */
    public function setBillingAddress(\Mage_Sales_Model_Quote $quote, array $addressData, bool $sameAsShipping = false): \Mage_Sales_Model_Quote
    {
        if ($sameAsShipping) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getCountryId()) {
                throw new \Mage_Core_Exception_Conflict('Cart has no shipping address to copy');
            }
            $addressData = ['save_in_address_book' => $addressData['save_in_address_book'] ?? false]
                + array_filter(
                    array_combine(self::ADDRESS_FIELDS, array_map($shippingAddress->getData(...), self::ADDRESS_FIELDS)),
                    static fn(mixed $value): bool => $value !== null,
                );
            $shippingAddress->setSameAsBilling(true);
        }

        $this->applyBillingAddress($quote, $addressData);
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Give the shipping address of $quote the shipping method of $carrierCode and $methodCode, and save the cart.
     *
     * @throws \Mage_Core_Exception_Input when a code is empty
     * @throws \Mage_Core_Exception when the address has no rate for the method, or the rate has an error
     */
    public function setShippingMethod(\Mage_Sales_Model_Quote $quote, string $carrierCode, string $methodCode): \Mage_Sales_Model_Quote
    {
        if ($carrierCode === '' || $methodCode === '') {
            throw new \Mage_Core_Exception_Input()->addError(
                $carrierCode === '' ? 'carrier_code' : 'method_code',
                \Mage::helper('checkout')->__('Carrier code and method code are required'),
            );
        }

        return $this->selectShippingMethod($quote, $carrierCode . '_' . $methodCode);
    }

    /**
     * Refuse a shipping method for which the shipping address of $quote has no rate. Collect the rates first.
     * A carrier that fails gives a rate with an error, which would price the shipment at 0.
     *
     * @throws \Mage_Core_Exception
     */
    public function assertShippingMethodAvailable(\Mage_Sales_Model_Quote $quote, string $shippingMethod): void
    {
        $rate = $quote->getShippingAddress()->getShippingRateByCode($shippingMethod);
        if (!$rate || $rate->getErrorMessage()) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Shipping method is not available for this address'));
        }
    }

    /**
     * Give $quote the payment method $methodCode with the flat scalar keys of $additionalData, and save the cart. It
     * is for a client that cannot send card data: it refuses a card method and removes the keys that
     * buildPaymentImportData() lists. $actor gives the checks of the method, see getPaymentChecks().
     *
     * @throws \Mage_Core_Exception_Input when $methodCode is empty
     * @throws \Mage_Core_Exception when the method is not available for the cart, or refuses the data
     */
    public function setPaymentMethod(
        \Mage_Sales_Model_Quote $quote,
        string $methodCode,
        ?array $additionalData = null,
        \Mage_Checkout_Model_Actor $actor = \Mage_Checkout_Model_Actor::Customer,
    ): \Mage_Sales_Model_Quote {
        if ($methodCode === '') {
            throw new \Mage_Core_Exception_Input()->addError('method_code', \Mage::helper('checkout')->__('Payment method code is required'));
        }
        $checks = $this->getPaymentChecks($actor);

        return $this->inQuoteStoreScope($quote, function () use ($quote, $methodCode, $additionalData, $checks): \Mage_Sales_Model_Quote {
            $this->assertPaymentMethodAvailable($quote, $methodCode);

            // Suppress importData()'s recollect; collectAndSave() below runs the real pass
            $quote->setTotalsCollectedFlag();
            $this->importPaymentData($quote, $methodCode, $additionalData, $checks);

            $this->collectAndSave($quote);

            return $quote;
        });
    }

    /**
     * Import the client payment data that buildPaymentImportData() accepts onto the payment of $quote, as
     * selectPaymentMethod() does, without a save, and keep the backup copy.
     *
     * @throws \Mage_Core_Exception when the method is not available for the cart, or refuses the data
     */
    public function importPaymentData(\Mage_Sales_Model_Quote $quote, string $methodCode, ?array $additionalData, int $checks): void
    {
        $paymentData = $this->buildPaymentImportData($methodCode, $additionalData, $checks);
        // Only a Mage_Core_Exception is the client's fault; anything else must surface as a 500
        try {
            $this->importPayment($quote, $paymentData);
        } catch (\Mage_Core_Exception $e) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Payment method is not available: %s', $e->getMessage()));
        }
        $this->backupPaymentAdditionalData($quote->getPayment(), $paymentData);
    }

    public function buildPaymentImportData(string $methodCode, ?array $additionalData, int $checks): array
    {
        // assignData() puts these flat keys straight onto the quote payment. The paypal_*
        // ids assert "already paid", and the storefront accepts one only after its replay
        // checks (assertPaypalOrderMatchesQuoteOrOrder), which this path never runs.
        $reservedKeys = array_flip([
            'method', 'checks', 'additional_data', 'additional_information', 'method_instance',
            'payment_id', 'quote_id', 'parent_id', 'created_at', 'updated_at',
            'paypal_order_id', 'paypal_authorization_id', 'paypal_capture_id',
        ]);
        $paymentData = ['method' => $methodCode];
        if ($additionalData) {
            // Flat scalars only: cc_* would carry raw card data, and a non-scalar under a
            // column-backed key (e.g. po_number) would corrupt the quote payment save
            $paymentData = array_merge(
                array_filter(
                    array_diff_key($additionalData, $reservedKeys),
                    fn(mixed $value, string|int $key): bool => (is_scalar($value) || $value === null)
                        && !str_starts_with((string) $key, 'cc_'),
                    ARRAY_FILTER_USE_BOTH,
                ),
                $paymentData,
            );
        }
        $paymentData['checks'] = $checks;

        return $paymentData;
    }

    /**
     * Keep the accepted client data, which importData() delivers flat and the save then drops
     * unless the method owns a column for it. Nesting keeps it away from assignData() and from
     * any top-level additional_information lookup.
     */
    public function backupPaymentAdditionalData(\Mage_Sales_Model_Quote_Payment $payment, array $paymentImportData): void
    {
        $backup = array_diff_key($paymentImportData, array_flip(['method', 'checks']));
        if ($backup) {
            $payment->setAdditionalInformation(self::PAYMENT_ADDITIONAL_DATA_KEY, $backup);
        } else {
            // Clear a previous selection's backup so a method switch cannot carry it onto the order
            $payment->unsAdditionalInformation(self::PAYMENT_ADDITIONAL_DATA_KEY);
        }
    }

    /**
     * Tell if $method can take a payment without card data. buildPaymentImportData() removes each cc_* key, so a
     * card method then always fails its validate().
     */
    public function isUsableWithoutCardData(\Mage_Payment_Model_Method_Abstract $method): bool
    {
        return !$method instanceof \Mage_Payment_Model_Method_Cc;
    }

    /**
     * Refuse a payment method that is not available for $quote, or that needs card data. importData() and
     * setMethod() alone accept any configured code.
     *
     * @throws \Mage_Core_Exception
     */
    public function assertPaymentMethodAvailable(\Mage_Sales_Model_Quote $quote, string $methodCode): void
    {
        $model = \Mage::helper('payment')->getPaymentMethods($quote->getStoreId())[$methodCode]['model'] ?? null;
        $method = $model ? \Mage::getModel($model) : null;
        if (!$method instanceof \Mage_Payment_Model_Method_Abstract || !$this->isUsableWithoutCardData($method)) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Payment method is not available for this cart'));
        }

        $method->setStore($quote->getStoreId());
        if (!$method->isAvailable($quote)) {
            throw new \Mage_Core_Exception(\Mage::helper('checkout')->__('Payment method is not available for this cart'));
        }
    }

    /**
     * Assignment changes the quote's customer group, so totals must be recollected.
     *
     * @throws \Mage_Core_Exception_Input when $customer does not exist
     */
    public function assignCustomer(\Mage_Sales_Model_Quote $quote, \Mage_Customer_Model_Customer $customer): \Mage_Sales_Model_Quote
    {
        if (!$customer->getId()) {
            throw new \Mage_Core_Exception_Input()->addError('customer_id', \Mage::helper('checkout')->__('Customer not found'));
        }
        $quote->assignCustomer($customer);
        $quote->setCustomerIsGuest(false);
        $this->collectAndSave($quote);

        return $quote;
    }

    /**
     * Merge the guest cart $guestMaskedId into the cart of the customer $customerId in the store $storeId, and close
     * the guest cart. A caller in a store view gives its store, so that its later cart lookups find the merged cart.
     * A caller with no store view gives null: then the cart of the store of the guest cart takes the items, so
     * that their prices stay.
     *
     * @throws \Mage_Core_Exception_Input when $guestMaskedId is empty
     * @throws \Mage_Core_Exception_NoSuchEntity when the guest cart does not exist or is the cart of another customer
     */
    public function merge(string $guestMaskedId, int $customerId, ?int $storeId = null): \Mage_Sales_Model_Quote
    {
        if ($guestMaskedId === '') {
            throw new \Mage_Core_Exception_Input()->addError('masked_id', \Mage::helper('checkout')->__('Masked cart ID is required'));
        }
        $guestCartId = $this->getCartIdFromMaskedId($guestMaskedId);
        if (!$guestCartId) {
            throw new \Mage_Core_Exception_NoSuchEntity('Guest cart not found');
        }
        // Use loadByIdWithoutStore, admin context may sit on a different store
        // than the guest cart, and store-scoped load() would return an empty
        // quote even though the masked-ID lookup just resolved successfully.
        $guestCart = \Mage::getModel('sales/quote')->loadByIdWithoutStore($guestCartId);

        if (!$guestCart->getId()) {
            throw new \Mage_Core_Exception_NoSuchEntity('Guest cart not found');
        }

        // Reject any masked ID that resolves to a cart owned by a different
        // customer, regardless of the customer_is_guest flag. A cart can carry
        // customer_is_guest=1 together with a foreign customer_id, which the
        // previous guard let slip through and allowed to be absorbed.
        $sourceCustomerId = $guestCart->getCustomerId();
        if ($sourceCustomerId && (int) $sourceCustomerId !== $customerId) {
            throw new \Mage_Core_Exception_NoSuchEntity('Guest cart not found');
        }

        $storeId = $storeId ?: (int) $guestCart->getStoreId() ?: (int) \Mage::app()->getStore()->getId();
        $customerCart = \Mage::app()->withStore($storeId, function () use ($guestCart, $customerId, $storeId): \Mage_Sales_Model_Quote {
            $customerCart = $this->getForCustomer($customerId, $storeId);
            $customerCart->merge($guestCart);

            // Import customer default addresses onto the cart so shipping quotes work
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if ($customer->getId()) {
                $defaultShipping = $customer->getDefaultShippingAddress();
                if ($defaultShipping && $defaultShipping->getId()) {
                    $shippingAddress = $customerCart->getShippingAddress();
                    if (!$shippingAddress->getFirstname()) {
                        $shippingAddress->importCustomerAddress($defaultShipping);
                        $shippingAddress->setSaveInAddressBook(false);
                    }
                }
                $defaultBilling = $customer->getDefaultBillingAddress();
                if ($defaultBilling && $defaultBilling->getId()) {
                    $billingAddress = $customerCart->getBillingAddress();
                    if (!$billingAddress->getFirstname()) {
                        $billingAddress->importCustomerAddress($defaultBilling);
                        $billingAddress->setSaveInAddressBook(false);
                    }
                }
            }
            $customerCart->setCustomerIsGuest(false);
            $this->collectAndSave($customerCart);

            return $customerCart;
        });

        // Deactivate guest cart
        $guestCart->setIsActive(false);
        $guestCart->save();

        return $customerCart;
    }

    /**
     * Return the cart of the customer $customerId after a login. A valid $guestMaskedId merges that guest cart into
     * the customer cart of the current store. A merge that fails does not fail the login: the customer then gets the active cart of the
     * current store, or null when there is none.
     */
    public function mergeAtLogin(mixed $guestMaskedId, int $customerId): ?\Mage_Sales_Model_Quote
    {
        if ($this->isValidMaskedId($guestMaskedId)) {
            try {
                return $this->merge($guestMaskedId, $customerId, (int) \Mage::app()->getStore()->getId());
            } catch (\Exception $e) {
                \Mage::log('Cart merge failed: ' . $e->getMessage(), \Mage::LOG_WARNING);
            }
        }

        $customerCart = \Mage::getModel('sales/quote')
            ->setSharedStoreIds([\Mage::app()->getStore()->getId()])
            ->loadByCustomer($customerId);
        return $customerCart->getId() ? $customerCart : null;
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
        if (!$this->isValidMaskedId($maskedId)) {
            return null;
        }

        // Masked IDs are always generated lowercase (bin2hex); normalize so the
        // lookup is deterministic regardless of the column collation and matches
        // the stored value no matter how the caller cased the input.
        $maskedId = strtolower($maskedId);

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
     * Recollect totals in the quote's store scope, after priming the quote
     * addresses and clearing their cached item lists.
     */
    public function collectAndVerifyTotals(\Mage_Sales_Model_Quote $quote): void
    {
        $this->inQuoteStoreScope($quote, fn() => self::collectTotalsInCurrentScope($quote));
    }

    /**
     * The totals-collection body; callers must already hold the quote's store scope.
     */
    private static function collectTotalsInCurrentScope(\Mage_Sales_Model_Quote $quote): void
    {
        self::primeQuoteForCollect($quote);
        $quote->collectTotals();
    }

    /**
     * Prepare the quote so the next collectTotals() call runs and is correct:
     * prime the addresses, drop stale address item caches, clear the
     * totals-collected flag.
     */
    private static function primeQuoteForCollect(\Mage_Sales_Model_Quote $quote): void
    {
        // Ensure quote has addresses, collectTotals() calculates per-address,
        // so without addresses all totals (including discounts) return 0
        $quote->getBillingAddress();
        $quote->getShippingAddress();

        // Clear address item cache, getAllItems() caches its result, so if collectTotals
        // was called before the item was added (e.g. during cart creation), the cache is stale.
        foreach ($quote->getAllAddresses() as $address) {
            $address->unsetData('cached_items_all');
        }

        $quote->setTotalsCollectedFlag(false);
    }

    /**
     * Recollect totals and persist the quote, both in its own store scope.
     */
    public function collectAndSave(\Mage_Sales_Model_Quote $quote): void
    {
        $this->inQuoteStoreScope($quote, function () use ($quote): void {
            self::collectTotalsInCurrentScope($quote);
            $quote->save();
        });
    }

    /**
     * Run $callback in the quote's store scope, then restore the caller's:
     * a leaked switch sent admin MOTO orders as storefront 3DS sales (issue #1337).
     */
    public function inQuoteStoreScope(\Mage_Sales_Model_Quote $quote, \Closure $callback): mixed
    {
        if (!$quote->getStoreId()) {
            return $callback();
        }

        return \Mage::app()->withStore((int) $quote->getStoreId(), $callback);
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
