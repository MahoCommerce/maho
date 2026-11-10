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
     * @throws Mage_Core_Exception_NoSuchEntity when $item is not an item of $wishlist
     */
    public function removeItem(Wishlist $wishlist, Item $item): void
    {
        $this->assertItemOf($wishlist, $item);
        $item->delete();
        $wishlist->save();
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
