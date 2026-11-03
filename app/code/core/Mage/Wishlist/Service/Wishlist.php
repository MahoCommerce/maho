<?php

/**
 * Load the wishlist of a customer and add and remove its items, for the storefront, the admin and the API.
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
     * @throws Mage_Core_Exception_NoSuchEntity when $item is not an item of $wishlist
     */
    public function removeItem(Wishlist $wishlist, Item $item): void
    {
        $this->assertItemOf($wishlist, $item);
        $item->delete();
        $wishlist->save();
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
