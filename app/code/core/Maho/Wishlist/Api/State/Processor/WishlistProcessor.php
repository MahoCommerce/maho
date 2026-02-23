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

namespace Maho\Wishlist\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use Maho\Wishlist\Api\Resource\WishlistItem;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Service\CartService;
use Maho\Wishlist\Api\State\Provider\WishlistProvider;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Wishlist State Processor
 *
 * @implements ProcessorInterface<WishlistItem, WishlistItem|array|null>
 */
final class WishlistProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    private CartService $cartService;

    public function __construct(Security $security, CartService $cartService)
    {
        $this->security = $security;
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
        if ($operationName === 'addToWishlist') {
            $args = $context['args']['input'] ?? [];
            return $this->addToWishlist(
                (int) $args['productId'],
                (int) ($args['qty'] ?? 1),
                $args['description'] ?? null,
            );
        }

        if ($operationName === 'removeFromWishlist') {
            $args = $context['args']['input'] ?? [];
            return $this->removeFromWishlist((int) $args['itemId']);
        }

        if ($operationName === 'moveWishlistItemToCart') {
            $args = $context['args']['input'] ?? [];
            return $this->moveToCart(
                (int) $args['itemId'],
                (int) ($args['qty'] ?? 1),
            );
        }

        if ($operationName === 'syncWishlist') {
            $args = $context['args']['input'] ?? [];
            $addedItems = $this->syncWishlist($args['productIds'] ?? []);
            // GraphQL mutation expects a single WishlistItem
            if (!empty($addedItems)) {
                return $addedItems[0];
            }
            // Nothing was added — return first existing wishlist item
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
            $qty = (int) ($body['qty'] ?? $data->qty ?? 1);
            $cartId = $body['cartId'] ?? null;
            return $this->moveToCart($itemId, $qty, $cartId);
        }

        if ($operationName === 'sync_wishlist') {
            $body = $context['request']?->toArray() ?? [];
            $addedItems = $this->syncWishlist($body['productIds'] ?? []);
            // REST POST expects single resource — return first added or first existing
            if (!empty($addedItems)) {
                return $addedItems[0];
            }
            return $this->getFirstWishlistItem();
        }

        // Default POST - add to wishlist
        if ($data instanceof WishlistItem && $data->productId) {
            return $this->addToWishlist(
                $data->productId,
                $data->qty ?? 1,
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

        // Check if already in wishlist — use a fresh unfiltered collection query
        // (the wishlist's own getItemsCollection() applies setVisibilityFilter() which can miss items)
        // Must use setWishlist() instead of addFieldToFilter('wishlist_id') to initialize the typed property
        /** @var \Mage_Wishlist_Model_Item|null $existingItem */ /** @phpstan-ignore varTag.type */
        $existingItem = \Mage::getModel('wishlist/item')->getCollection()
            ->setWishlist($wishlist)
            ->addFieldToFilter('product_id', $productId)
            ->setPageSize(1)
            ->getFirstItem();

        if ($existingItem && $existingItem->getId()) {
            $existingItem->setQty($existingItem->getQty() + $qty);
            if ($description !== null) {
                $existingItem->setDescription($description);
            }
            $existingItem->save();
            $item = $existingItem;
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
            $item->setAddedAt(\Mage_Core_Model_Locale::now());
            $item->save();
        }

        $wishlist->save();
        WishlistProvider::invalidateCache($customerId);

        // Return the wishlist item
        return $this->buildWishlistItem($item, $product);
    }

    /**
     * Remove item from wishlist
     * @phpstan-ignore return.unusedType
     */
    private function removeFromWishlist(int $itemId): ?WishlistItem
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
        if (!$wishlist->getId() || (int) $wishlist->getCustomerId() !== $customerId) {
            throw new AccessDeniedHttpException('Access denied to this wishlist item');
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
        if (!$wishlist->getId() || (int) $wishlist->getCustomerId() !== $customerId) {
            throw new AccessDeniedHttpException('Access denied to this wishlist item');
        }

        $product = $item->getProduct();

        // Get the quote - prefer guest cart if cartId provided
        /** @var \Mage_Sales_Model_Quote|null $quote */
        $quote = null;
        if ($cartId) {
            // Use CartService to load the cart properly (handles numeric or masked IDs)
            if (is_numeric($cartId)) {
                $quote = $this->cartService->getCart((int) $cartId);
            } else {
                $quote = $this->cartService->getCart(null, $cartId);
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
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to add item to cart');
        }

        // Build response before deleting (we need the item data)
        $wishlistItem = $this->buildWishlistItem($item, $product);

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

        // Get existing product IDs in wishlist
        $existingProductIds = [];
        foreach ($wishlist->getItemsCollection() as $item) {
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
                $addedItems[] = $this->buildWishlistItem($item, $product);
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
                return $this->buildWishlistItem($item, $product);
            }
        }

        // Empty wishlist — return a placeholder with wishlist ID
        $placeholder = new WishlistItem();
        $placeholder->id = (int) $wishlist->getId();
        return $placeholder;
    }

    /**
     * Build WishlistItem from model
     */
    private function buildWishlistItem(\Mage_Wishlist_Model_Item $item, \Mage_Catalog_Model_Product $product): WishlistItem
    {
        $wishlistItem = new WishlistItem();
        $wishlistItem->id = (int) $item->getId();
        $wishlistItem->productId = (int) $product->getId();
        $wishlistItem->productName = $product->getName();
        $wishlistItem->productSku = $product->getSku();
        $wishlistItem->productPrice = (float) $product->getFinalPrice();
        $wishlistItem->productImageUrl = $this->getProductImageUrl($product);
        $wishlistItem->productUrl = '/' . ($product->getUrlKey() ?: $product->formatUrlKey($product->getName()));
        $wishlistItem->productType = $product->getTypeId();
        $wishlistItem->qty = (int) ($item->getQty() ?: 1);
        $wishlistItem->description = $item->getDescription();
        $wishlistItem->addedAt = $item->getAddedAt();
        $wishlistItem->inStock = (bool) $product->isInStock();

        return $wishlistItem;
    }

    // TODO: Extract getProductImageUrl() to a shared trait or service to eliminate duplication with WishlistProvider/WishlistProcessor
    /**
     * Get product thumbnail URL
     */
    private function getProductImageUrl(\Mage_Catalog_Model_Product $product): string
    {
        try {
            return (string) \Mage::helper('catalog/image')
                ->init($product, 'small_image')
                ->resize(300);
        } catch (\Exception $e) {
            return '';
        }
    }
}
