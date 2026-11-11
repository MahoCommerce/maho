<?php

/**
 * Load the wishlist of a customer, add, change and remove its items, and move them to the cart, for the storefront, the
 * admin and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

use Mage_Wishlist_Model_Item as Item;
use Mage_Wishlist_Model_Wishlist as Wishlist;

class Mage_Wishlist_Service_Wishlist
{
    public const XML_PATH_ENABLED = 'wishlist/general/active';
    public const XML_PATH_SHARE_RATE_LIMIT = 'system/rate_limit/wishlist_share';

    public const SHARE_RATE_LIMIT_WINDOW = 3600;
    public const SHARE_MAX_RECIPIENTS = 5;

    public function isEnabled(int $storeId): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * Return the wishlist of the customer $customerId, and create it when the customer has none.
     * With $wishlistId, the wishlist of the customer must have this ID.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store has no wishlist, or $wishlistId is not the wishlist of the customer
     */
    public function getForCustomer(int $customerId, int $storeId, ?int $wishlistId = null): Wishlist
    {
        $helper = Mage::helper('wishlist');
        if (!$this->isEnabled($storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('The wishlist is not available.'));
        }
        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId, true);
        if ($wishlistId !== null && $wishlistId !== (int) $wishlist->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__("Requested wishlist doesn't exist"));
        }
        return $wishlist;
    }

    /**
     * Return the wishlist that the owner shared with the code $code.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store has no wishlist, or no shared wishlist has this code
     */
    public function getByCode(string $code, int $storeId): Wishlist
    {
        $helper = Mage::helper('wishlist');
        if (!$this->isEnabled($storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('The wishlist is not available.'));
        }
        $wishlist = Mage::getModel('wishlist/wishlist');
        if ($code !== '') {
            $wishlist->loadByCode($code);
        }
        if (!$wishlist->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__("Requested wishlist doesn't exist"));
        }
        return $wishlist;
    }

    /**
     * Return the item $itemId of $wishlist, with its options.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when $itemId is not an item of $wishlist
     */
    public function getItem(Wishlist $wishlist, int $itemId): Item
    {
        $item = Mage::getModel('wishlist/item')->loadWithOptions($itemId);
        $this->assertItemOf($wishlist, $item);
        return $item->setWishlist($wishlist);
    }

    /**
     * Return the product $productId when the storefront of the store $storeId shows it.
     *
     * @throws Mage_Core_Exception
     */
    public function getProduct(int $productId, int $storeId): Mage_Catalog_Model_Product
    {
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        if (!Mage::helper('catalog/product')->isVisibleInStore($product, $storeId)) {
            throw new Mage_Core_Exception(Mage::helper('wishlist')->__('Product not found'));
        }
        return $product;
    }

    /**
     * Add the product $productId to $wishlist with the options of $buyRequest. An item of the same product with the
     * same options gets the quantity added. $description replaces the comment of the item when it is not null.
     *
     * @throws Mage_Core_Exception when the store does not show the product, or the options are not valid
     */
    public function addProduct(
        Wishlist $wishlist,
        int $productId,
        \Maho\DataObject $buyRequest,
        int $storeId,
        ?string $description = null,
    ): Item {
        $product = $this->getProduct($productId, $storeId);
        $item = $wishlist->addNewItem($product, $buyRequest);
        if (is_string($item)) {
            throw new Mage_Core_Exception($item);
        }
        if ($description !== null) {
            $item->setDescription($description);
        }
        $wishlist->save();

        Mage::dispatchEvent('wishlist_add_product', ['wishlist' => $wishlist, 'product' => $product, 'item' => $item]);
        return $item;
    }

    /**
     * Add each product of $productIds that $wishlist does not hold and that the store shows. Skip the others.
     *
     * @param array<int|string> $productIds
     * @return list<Item> the new items
     */
    public function addProducts(Wishlist $wishlist, array $productIds, int $storeId): array
    {
        // Not getItemsCollection(): it hides the items of a product that the store does not show
        $existing = Mage::getModel('wishlist/item')->getCollection()->setWishlist($wishlist)->getColumnValues('product_id');
        $items = [];
        foreach (array_diff(array_unique(array_map(intval(...), $productIds)), array_map(intval(...), $existing)) as $productId) {
            try {
                $items[] = $this->addProduct($wishlist, $productId, new \Maho\DataObject(), $storeId);
            } catch (Mage_Core_Exception) {
                continue;
            }
        }
        return $items;
    }

    /**
     * Give $item the options and the qty of $buyRequest, and save $wishlist. A qty of 0 removes the item. An item that
     * gets the options of another item of the same product joins that item.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when $item is not an item of $wishlist
     * @throws Mage_Core_Exception when the product refuses the options
     */
    public function updateItem(Wishlist $wishlist, Item $item, \Maho\DataObject $buyRequest): void
    {
        $this->assertItemOf($wishlist, $item);
        if ($this->isRemoval($buyRequest->getQty())) {
            $this->removeItem($wishlist, $item);
            return;
        }

        $product = $item->getProduct();
        $wishlist->updateItem((int) $item->getId(), $buyRequest)->save();
        Mage::dispatchEvent('wishlist_update_item', [
            'wishlist' => $wishlist,
            'product' => $product,
            'item' => $wishlist->getItemById((int) $item->getId()),
        ]);
    }

    /**
     * Give each item of $changes its description and its qty, and save $wishlist once. A qty of 0 removes the item, and
     * a null value keeps the current one. Skip an ID that is not an item of $wishlist.
     *
     * @param array<int, array{description?: ?string, qty?: ?float}> $changes by item ID
     * @return int the number of items that changed
     */
    public function updateItems(Wishlist $wishlist, array $changes): int
    {
        $changed = 0;
        foreach ($changes as $itemId => $change) {
            $item = Mage::getModel('wishlist/item')->load((int) $itemId);
            if (!$item->getId() || $item->getWishlistId() !== (int) $wishlist->getId()) {
                continue;
            }
            $qty = $change['qty'] ?? null;
            if ($this->isRemoval($qty)) {
                $item->delete();
                $changed++;
                continue;
            }

            $description = $change['description'] ?? (string) $item->getDescription();
            $qty ??= $item->getQty() ?: 1.0;
            if ((string) $item->getDescription() === $description && (float) $item->getQty() === (float) $qty) {
                continue;
            }
            $item->setDescription($description)->setQty($qty)->save();
            $changed++;
        }
        if ($changed) {
            $wishlist->save();
        }
        return $changed;
    }

    /**
     * Add $item to $quote with the options of its buy request and $qty, and save the cart. Remove the item from
     * $wishlist, unless $keepItem is true, as for a wishlist that the customer does not own.
     *
     * @param float|null $qty null takes the qty of the item
     * @throws Mage_Core_Exception_NoSuchEntity when $item is not an item of $wishlist
     * @throws Mage_Core_Exception see Mage_Checkout_Service_Cart::addProduct()
     */
    public function moveToCart(
        Wishlist $wishlist,
        Item $item,
        \Mage_Sales_Model_Quote $quote,
        ?float $qty = null,
        bool $keepItem = false,
    ): \Mage_Sales_Model_Quote_Item {
        $this->assertItemOf($wishlist, $item);
        $buyRequest = $item->getBuyRequest();
        if ($qty !== null) {
            $buyRequest->setQty($qty);
        }
        $cartItem = Mage::getService('checkout/cart')->addProduct($quote, $item->getProduct(), $buyRequest);

        if (!$keepItem) {
            $this->removeItem($wishlist, $item);
        }
        return $cartItem;
    }

    /**
     * Add the product of the cart line $itemId of $quote to $wishlist with the options of the line, then remove the
     * line and save the cart. The item gets no value of a file option, because the file belongs to the cart line.
     * The cart line stays when the wishlist refuses the product.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the cart has no line $itemId
     * @throws Mage_Core_Exception see addProduct()
     */
    public function moveFromCart(Wishlist $wishlist, \Mage_Sales_Model_Quote $quote, int $itemId): Item
    {
        $cartItem = $quote->getItemById($itemId);
        if (!$cartItem) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('wishlist')->__("Requested cart item doesn't exist"));
        }

        $buyRequest = $cartItem->getBuyRequest();
        $options = $buyRequest->getOptions();
        if (is_array($options)) {
            foreach ($cartItem->getProduct()->getOptions() as $option) {
                if ($option->getType() === Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE) {
                    unset($options[$option->getId()]);
                }
            }
            $buyRequest->setOptions($options);
        }

        $item = $this->addProduct($wishlist, (int) $cartItem->getProductId(), $buyRequest, (int) $quote->getStoreId());
        Mage::getService('checkout/cart')->removeItem($quote, $itemId);
        return $item;
    }

    /**
     * Return whether $product has a custom option of the type file.
     */
    public function hasFileOption(Mage_Catalog_Model_Product $product): bool
    {
        return array_any(
            $product->getOptions(),
            fn(Mage_Catalog_Model_Product_Option $option): bool => $option->getType() === Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE,
        );
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when $item is not an item of $wishlist
     */
    public function removeItem(Wishlist $wishlist, Item $item): void
    {
        $this->assertItemOf($wishlist, $item);
        $item->delete();
        $wishlist->save();
    }

    /**
     * Send the link and the items of $wishlist to each address of $emails, with $message of the customer, and mark
     * the wishlist as shared. With $withRssLink, the email also has the link to the RSS feed of the wishlist.
     *
     * @param array<string> $emails 1 to 5 addresses
     * @throws Mage_Core_Exception_Input when $emails is empty, has more than 5 addresses, or has an address that is not valid
     * @throws Mage_Core_Exception_NoSuchEntity when the customer of the wishlist does not exist
     * @throws Mage_Core_Exception_TooManyRequests when the customer is over the limit
     */
    public function share(Wishlist $wishlist, array $emails, string $message, int $storeId, bool $withRssLink = false): Wishlist
    {
        $helper = Mage::helper('wishlist');
        $core = Mage::helper('core');

        $emails = array_values(array_unique(array_filter(array_map(fn(#[\SensitiveParameter]
            mixed $email): string => trim((string) $email), $emails))));
        $error = match (true) {
            $emails === [] => $helper->__("Email address can't be empty."),
            count($emails) > self::SHARE_MAX_RECIPIENTS => $helper->__('Please enter no more than 5 email addresses.'),
            !array_all($emails, $core->isValidEmail(...)) => $helper->__('Please input a valid email address.'),
            default => null,
        };
        if ($error !== null) {
            throw new Mage_Core_Exception_Input()->addError('emails', $error);
        }

        $customerId = (int) $wishlist->getCustomerId();
        $customer = Mage::getService('customer/customer')->getById($customerId)
            ?? throw new Mage_Core_Exception_NoSuchEntity($helper->__('Customer not found'));

        $limit = (int) Mage::getStoreConfig(self::XML_PATH_SHARE_RATE_LIMIT, $storeId);
        $limiter = $core->rateLimiterBy('wishlist_share', "customer:{$customerId}", $limit, self::SHARE_RATE_LIMIT_WINDOW);
        if (!$limiter->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests(
                $helper->__('Too many requests. Please try again later.'),
                self::SHARE_RATE_LIMIT_WINDOW,
            );
        }

        $message = nl2br(htmlspecialchars($message));
        $emulation = Mage::getSingleton('core/app_emulation');
        $environment = $emulation->startEnvironmentEmulation($storeId);
        try {
            $layout = Mage::app()->getLayout();
            if ($withRssLink) {
                $helper->setCustomer($customer);
                $message .= $layout->createBlock('wishlist/share_email_rss')->setWishlistId((int) $wishlist->getId())->toHtml();
            }
            $variables = [
                'customer' => $customer,
                'items' => $layout->createBlock('wishlist/share_email_items')->setWishlist($wishlist)->toHtml(),
                'viewOnSiteLink' => Mage::getUrl('wishlist/shared/index', ['code' => $wishlist->getSharingCode()]),
                'message' => $message,
            ];
        } finally {
            $emulation->stopEnvironmentEmulation($environment);
        }

        $template = Mage::getModel('core/email_template');
        foreach ($emails as $email) {
            $template->sendTransactional(
                Mage::getStoreConfig('wishlist/email/email_template', $storeId),
                Mage::getStoreConfig('wishlist/email/email_identity', $storeId),
                $email,
                null,
                $variables,
                $storeId,
            );
        }

        $wishlist->setShared()->save();
        Mage::dispatchEvent('wishlist_share', ['wishlist' => $wishlist]);
        return $wishlist;
    }

    private function isRemoval(mixed $qty): bool
    {
        return is_numeric($qty) && (float) $qty === 0.0;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function assertItemOf(Wishlist $wishlist, Item $item): void
    {
        // The same answer for an item of another customer, so that a client cannot find which item IDs exist
        if (!$item->getId() || $item->getWishlistId() !== (int) $wishlist->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('wishlist')->__('Wishlist item not found'));
        }
    }
}
