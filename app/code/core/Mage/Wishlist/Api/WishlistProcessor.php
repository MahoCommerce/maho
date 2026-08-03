<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

namespace Mage\Wishlist\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Delete;
use Maho\ApiPlatform\Service\StoreContext;
use Mage\Checkout\Api\CartService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Wishlist State Processor.
 */
final class WishlistProcessor extends \Maho\ApiPlatform\Processor
{
    private CartService $cartService;

    public function __construct(Security $security, CartService $cartService)
    {
        parent::__construct($security);
        $this->cartService = $cartService;
    }

    /**
     * @param WishlistItem $data
     * @return WishlistItem|null
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL mutations
        if ($operationName === 'add') {
            $args = $context['args']['input'] ?? [];
            return $this->addToWishlist(
                (int) $args['productId'],
                (int) ($args['qty'] ?? 1),
                $args['description'] ?? null,
            );
        }

        if ($operationName === 'remove') {
            $args = $context['args']['input'] ?? [];
            return $this->removeFromWishlist((int) $args['itemId']);
        }

        if ($operationName === 'moveToCart') {
            $args = $context['args']['input'] ?? [];
            return $this->moveToCart(
                (int) $args['itemId'],
                (int) ($args['qty'] ?? 1),
            );
        }

        if ($operationName === 'sync') {
            $args = $context['args']['input'] ?? [];
            $addedItems = $this->syncWishlist($args['productIds'] ?? []);
            // GraphQL mutation expects a single WishlistItem
            if (!empty($addedItems)) {
                return $addedItems[0];
            }
            // Nothing was added, return first existing wishlist item
            return $this->getFirstWishlistItem();
        }

        // REST operations
        if ($operation instanceof Delete) {
            $itemId = (int) ($uriVariables['id'] ?? 0);
            return $this->removeFromWishlist($itemId);
        }

        if ($operationName === 'move_to_cart') {
            $itemId = (int) ($uriVariables['id'] ?? 0);
            $body = $context['request']?->toArray() ?? [];
            $qty = (int) ($body['qty'] ?? $data->qty);
            // JSON numbers arrive as int; moveToCart types $cartId as ?string.
            $cartId = isset($body['cartId']) ? (string) $body['cartId'] : null;
            return $this->moveToCart($itemId, $qty, $cartId);
        }

        if ($operationName === 'sync_wishlist') {
            $body = $context['request']?->toArray() ?? [];
            $addedItems = $this->syncWishlist($body['productIds'] ?? []);
            // REST POST expects single resource, return first added or first existing
            if (!empty($addedItems)) {
                return $addedItems[0];
            }
            return $this->getFirstWishlistItem();
        }

        // Default POST - add to wishlist
        if ($data instanceof WishlistItem && $data->productId) {
            return $this->addToWishlist(
                $data->productId,
                $data->qty,
                $data->description,
            );
        }

        throw new BadRequestHttpException('Invalid wishlist operation');
    }

    /**
     * Get or create customer wishlist
     */
    private function getWishlist(int $customerId): \Mage_Wishlist_Model_Wishlist
    {
        /** @var \Mage_Wishlist_Model_Wishlist $wishlist */
        $wishlist = \Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId, true);
        // loadByCustomer(create: true) instantiates but does NOT persist a new
        // wishlist, so its id is null. Attaching an item now would set
        // wishlist_id = (int) null = 0, orphaning it from the customer's real
        // wishlist. Persist first so the item binds to a valid wishlist id.
        if (!$wishlist->getId()) {
            $wishlist->save();
        }
        return $wishlist;
    }

    /**
     * Add product to wishlist
     */
    private function addToWishlist(int $productId, int $qty = 1, ?string $description = null): WishlistItem
    {
        $customerId = $this->requireAuthentication();

        // Load product
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product')->load($productId);
        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $wishlist = $this->getWishlist($customerId);

        // Check if already in wishlist, use a fresh unfiltered collection query
        // (the wishlist's own getItemsCollection() applies setVisibilityFilter() which can miss items)
        // Must use setWishlist() instead of addFieldToFilter('wishlist_id') to initialize the typed property
        /** @var \Mage_Wishlist_Model_Item $existingItem */
        $existingItem = \Mage::getModel('wishlist/item')->getCollection()
            ->setWishlist($wishlist)
            ->addFieldToFilter('product_id', $productId)
            ->setPageSize(1)
            ->getFirstItem();

        if ($existingItem && $existingItem->getId()) {
            // Re-load the item as a clean standalone model. The collection-loaded
            // instance carries joined product data and reset-changed flags whose
            // save() round-trip drops the row; a fresh load updates cleanly.
            /** @var \Mage_Wishlist_Model_Item $item */
            $item = \Mage::getModel('wishlist/item')->load((int) $existingItem->getId());
            $item->setQty($item->getQty() + $qty);
            if ($description !== null) {
                $item->setDescription($description);
            }
            $item->save();
        } else {
            // Add new item directly (skip core's addNewItem which has its own flawed dedup)
            /** @var \Mage_Wishlist_Model_Item $item */
            $item = \Mage::getModel('wishlist/item');
            $item->setWishlistId((int) $wishlist->getId());
            $item->setProductId($productId);
            $item->setStoreId((int) \Mage::app()->getStore()->getId());
            $item->setQty($qty);
            if ($description !== null) {
                $item->setDescription($description);
            }
            $item->setAddedAt(\Mage::app()->getLocale()->formatDateForDb('now'));
            $item->save();
        }

        $wishlist->save();
        WishlistProvider::invalidateCache($customerId);

        // Return the wishlist item
        return WishlistItem::fromModel($item);
    }

    /**
     * Remove item from wishlist
     */
    private function removeFromWishlist(int $itemId): null
    {
        $customerId = $this->requireAuthentication();

        /** @var \Mage_Wishlist_Model_Item $item */
        $item = \Mage::getModel('wishlist/item')->load($itemId);

        if (!$item->getId()) {
            throw new NotFoundHttpException('Wishlist item not found');
        }

        // Verify ownership - load wishlist explicitly
        $wishlistId = $item->getWishlistId();
        /** @var \Mage_Wishlist_Model_Wishlist $wishlist */
        $wishlist = \Mage::getModel('wishlist/wishlist')->load($wishlistId);
        // Reported as missing rather than forbidden, so the endpoint cannot be
        // used to probe which wishlist item ids exist.
        if (!$wishlist->getId() || (int) $wishlist->getCustomerId() !== $customerId) {
            throw new NotFoundHttpException('Wishlist item not found');
        }

        $item->delete();
        WishlistProvider::invalidateCache($customerId);

        return null;
    }

    // TODO: Refactor cart loading to use CartService instead of inline quote loading logic
    /**
     * Move wishlist item to cart
     */
    private function moveToCart(int $itemId, int $qty = 1, ?string $cartId = null): WishlistItem
    {
        $customerId = $this->requireAuthentication();

        /** @var \Mage_Wishlist_Model_Item $item */
        $item = \Mage::getModel('wishlist/item')->load($itemId);

        if (!$item->getId()) {
            throw new NotFoundHttpException('Wishlist item not found');
        }

        // Verify ownership - load wishlist explicitly
        $wishlistId = $item->getWishlistId();
        /** @var \Mage_Wishlist_Model_Wishlist $wishlist */
        $wishlist = \Mage::getModel('wishlist/wishlist')->load($wishlistId);
        // Reported as missing rather than forbidden, so the endpoint cannot be
        // used to probe which wishlist item ids exist.
        if (!$wishlist->getId() || (int) $wishlist->getCustomerId() !== $customerId) {
            throw new NotFoundHttpException('Wishlist item not found');
        }

        $product = $item->getProduct();

        // Get the quote - prefer guest cart if cartId provided
        /** @var \Mage_Sales_Model_Quote|null $quote */
        $quote = null;
        if ($cartId) {
            // Use CartService to load the cart properly (handles numeric or masked IDs)
            $accessedByMaskedId = !is_numeric($cartId);
            if ($accessedByMaskedId) {
                $quote = $this->cartService->getCart(null, $cartId);
            } else {
                $quote = $this->cartService->getCart((int) $cartId);
            }

            // getCart() applies no ownership filtering, verify the caller owns
            // this cart (or holds its masked guest token) before writing to it,
            // otherwise a customer could push items into another customer's cart.
            if ($quote && $quote->getId()) {
                $this->cartService->verifyCartAccess(
                    $quote,
                    $accessedByMaskedId,
                    $customerId,
                    $this->isAdmin() || $this->isApiUser(),
                );
            }
        }

        if (!$quote || !$quote->getId()) {
            // Fall back to customer's active quote
            $quote = \Mage::getModel('sales/quote')
                ->setSharedStoreIds([\Mage::app()->getStore()->getId()])
                ->loadByCustomer($customerId);

            if (!$quote->getId()) {
                // Create new quote for customer
                $quote = \Mage::getModel('sales/quote');
                $quote->setStoreId(\Mage::app()->getStore()->getId());
                $quote->setCustomerId($customerId);
                $quote->setIsActive(1);
                $quote->save();
            }
        }

        // Add to cart using CartService
        try {
            $this->cartService->addItem($quote, $product->getSku(), (float) $qty);
        } catch (\Exception) {
            throw new BadRequestHttpException('Failed to add item to cart');
        }

        // Build response before deleting (we need the item data)
        $wishlistItem = WishlistItem::fromModel($item);

        // Remove from wishlist
        $item->delete();
        WishlistProvider::invalidateCache($customerId);

        return $wishlistItem;
    }

    /**
     * Sync guest wishlist (from localStorage) with customer account
     *
     * @param array<int> $productIds
     * @return array<WishlistItem>
     */
    private function syncWishlist(array $productIds): array
    {
        $customerId = $this->requireAuthentication();
        $wishlist = $this->getWishlist($customerId);

        // Get existing product IDs in wishlist. Use a fresh unfiltered collection
        // query: $wishlist->getItemsCollection() applies setVisibilityFilter(),
        // which hides items whose product is currently disabled/invisible and
        // would let us re-add them as duplicates below.
        $existingProductIds = [];
        $existingItems = \Mage::getModel('wishlist/item')->getCollection()
            ->setWishlist($wishlist);
        foreach ($existingItems as $item) {
            $existingProductIds[] = (int) $item->getProductId();
        }

        $addedItems = [];

        foreach ($productIds as $productId) {
            $productId = (int) $productId;

            // Skip if already in wishlist
            if (in_array($productId, $existingProductIds, true)) {
                continue;
            }

            /** @var \Mage_Catalog_Model_Product $product */
            $product = \Mage::getModel('catalog/product')->load($productId);
            if (!$product->getId()) {
                continue;
            }

            $item = $wishlist->addNewItem($product);
            if ($item instanceof \Mage_Wishlist_Model_Item) {
                $item->save();
                $addedItems[] = WishlistItem::fromModel($item);
            }
        }

        $wishlist->save();
        WishlistProvider::invalidateCache($customerId);

        return $addedItems;
    }

    /**
     * Get the first item from the customer's wishlist (for mutation return when no new items added)
     */
    private function getFirstWishlistItem(): WishlistItem
    {
        $customerId = $this->requireAuthentication();
        $wishlist = $this->getWishlist($customerId);

        $itemCollection = $wishlist->getItemsCollection();
        $itemCollection->addStoreFilter([\Mage::app()->getStore()->getId()]);

        /** @var \Mage_Wishlist_Model_Item $item */
        foreach ($itemCollection as $item) {
            $product = $item->getProduct();
            if ($product && $product->getId()) {
                return WishlistItem::fromModel($item);
            }
        }

        // Empty wishlist, return a placeholder with wishlist ID
        $placeholder = new WishlistItem();
        $placeholder->id = (int) $wishlist->getId();
        return $placeholder;
    }
}
