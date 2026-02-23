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

namespace Maho\Wishlist\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\Wishlist\Api\Resource\WishlistItem;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Wishlist State Provider
 *
 * @implements ProviderInterface<WishlistItem>
 */
final class WishlistProvider implements ProviderInterface
{
    use AuthenticationTrait;

    /**
     * Wishlists are per-user and mutate often — short TTL
     */
    private function getCacheTtl(): int
    {
        return max(60, (int) (\Maho_ApiPlatform_Model_Observer::getCacheTtl() / 3));
    }

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|WishlistItem|null
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL collection operations need ArrayPaginator
        if ($operationName === 'myWishlist' || $operationName === 'collection_query') {
            $items = $this->getWishlistItems();
            return new ArrayPaginator(items: $items, currentPage: 1, itemsPerPage: max(count($items), 50), totalItems: count($items));
        }

        // REST collection - get wishlist items
        if ($operation instanceof CollectionOperationInterface) {
            $items = $this->getWishlistItems();
            return new ArrayPaginator(items: $items, currentPage: 1, itemsPerPage: 50, totalItems: count($items));
        }

        // Single item lookup
        $itemId = (int) ($uriVariables['id'] ?? 0);
        if ($itemId) {
            return $this->getItem($itemId);
        }

        return null;
    }

    /**
     * Get customer's wishlist items
     *
     * @return array<WishlistItem>
     */
    private function getWishlistItems(): array
    {
        $customerId = $this->requireAuthentication();
        $storeId = \Mage::app()->getStore()->getId();
        $cacheKey = "api_wishlist_{$customerId}_{$storeId}";

        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                return array_map(fn(array $d) => $this->arrayToWishlistDto($d), $data);
            }
        }

        /** @var \Mage_Wishlist_Model_Wishlist $wishlist */
        $wishlist = \Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId, true);

        if (!$wishlist->getId()) {
            return [];
        }

        $items = [];
        $itemCollection = $wishlist->getItemsCollection();
        $itemCollection->addStoreFilter([$storeId]);

        /** @var \Mage_Wishlist_Model_Item $item */
        foreach ($itemCollection as $item) {
            $product = $item->getProduct();
            if (!$product || !$product->getId()) {
                continue;
            }

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

            $items[] = $wishlistItem;
        }

        \Mage::app()->getCache()->save(
            (string) json_encode(array_map(fn(WishlistItem $i) => $this->wishlistDtoToArray($i), $items)),
            $cacheKey,
            ['API_WISHLIST', "API_WISHLIST_{$customerId}"],
            $this->getCacheTtl(),
        );

        return $items;
    }

    /**
     * Get single wishlist item
     */
    private function getItem(int $itemId): WishlistItem
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

    /**
     * @return array<string, mixed>
     */
    private function wishlistDtoToArray(WishlistItem $item): array
    {
        return [
            'id' => $item->id,
            'productId' => $item->productId,
            'productName' => $item->productName,
            'productSku' => $item->productSku,
            'productPrice' => $item->productPrice,
            'productImageUrl' => $item->productImageUrl,
            'productUrl' => $item->productUrl,
            'productType' => $item->productType,
            'qty' => $item->qty,
            'description' => $item->description,
            'addedAt' => $item->addedAt,
            'inStock' => $item->inStock,
        ];
    }

    private function arrayToWishlistDto(array $data): WishlistItem
    {
        $item = new WishlistItem();
        $item->id = (int) $data['id'];
        $item->productId = (int) $data['productId'];
        $item->productName = $data['productName'];
        $item->productSku = $data['productSku'];
        $item->productPrice = (float) $data['productPrice'];
        $item->productImageUrl = $data['productImageUrl'] ?? '';
        $item->productUrl = $data['productUrl'] ?? '';
        $item->productType = $data['productType'] ?? 'simple';
        $item->qty = (int) ($data['qty'] ?? 1);
        $item->description = $data['description'] ?? null;
        $item->addedAt = $data['addedAt'] ?? null;
        $item->inStock = (bool) ($data['inStock'] ?? true);

        return $item;
    }

    /**
     * Invalidate wishlist cache for a customer
     */
    public static function invalidateCache(int $customerId): void
    {
        \Mage::app()->getCache()->clean(["API_WISHLIST_{$customerId}"]);
    }
}
