<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

namespace Mage\Wishlist\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\CustomerOwnership;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Wishlist State Provider.
 */
final class WishlistProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * Wishlists are per-user and mutate often, short TTL
     */
    private function getCacheTtl(): int
    {
        return max(60, (int) (\Maho_ApiPlatform_Model_Observer::getCacheTtl() / 3));
    }

    /**
     * @return TraversablePaginator<WishlistItem>|WishlistItem|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|WishlistItem|null
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL collection operations need TraversablePaginator
        if ($operationName === 'my' || $operationName === 'collection_query') {
            $items = $this->getWishlistItems();
            return new TraversablePaginator(new \ArrayIterator($items), 1, max(count($items), 50), count($items));
        }

        // REST collection - get wishlist items
        if ($operation instanceof CollectionOperationInterface) {
            $items = $this->getWishlistItems();
            return new TraversablePaginator(new \ArrayIterator($items), 1, 50, count($items));
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
        $customerId = $this->requireCustomerId();
        $storeId = (int) \Mage::app()->getStore()->getId();
        $wishlist = \Mage::getService('wishlist/wishlist')->getForCustomer($customerId, $storeId);
        $groupId = \Mage::helper('customer')->getCurrentGroupId();
        $cacheKey = "api_wishlist_{$customerId}_{$storeId}_{$groupId}";

        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            try {
                $data = \Mage::helper('core')->jsonDecode($cached);
            } catch (\JsonException) {
                $data = null;
            }
            if (is_array($data)) {
                return array_map($this->arrayToWishlistDto(...), $data);
            }
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

            $dto = WishlistItem::fromModel($item);
            $dto->customerId = $customerId;
            $items[] = $dto;
        }

        \Mage::app()->getCache()->save(
            (string) \Mage::helper('core')->jsonEncode(array_map($this->wishlistDtoToArray(...), $items)),
            $cacheKey,
            ['API_WISHLIST', "API_WISHLIST_{$customerId}"],
            $this->getCacheTtl(),
        );

        // Dispatch after the cache save. A cache hit dispatches the event again,
        // so the cache must not keep the changes of an observer.
        foreach ($items as $dto) {
            \Mage::dispatchEvent('api_wishlist_item_dto_build', ['dto' => $dto]);
        }

        return $items;
    }

    /**
     * Get single wishlist item. Ownership is not checked here: the parent
     * wishlist's customer id is set on the DTO and the operation's
     * `is_owner(object, 'customerId')` expression does the denying post-read.
     */
    private function getItem(int $itemId): WishlistItem
    {
        /** @var \Mage_Wishlist_Model_Item $item */
        $item = \Mage::getModel('wishlist/item')->load($itemId);

        /** @var \Mage_Wishlist_Model_Wishlist $wishlist */
        $wishlist = \Mage::getModel('wishlist/wishlist')->load((int) $item->getWishlistId());
        if (!$item->getId() || !$wishlist->getId()) {
            throw new \Mage_Core_Exception_NoSuchEntity('Wishlist item not found');
        }

        $dto = WishlistItem::fromModel($item);
        $dto->customerId = $wishlist->getCustomerId() ? (int) $wishlist->getCustomerId() : null;
        // The operation security refuses other callers after the read, so their observers must not run.
        if (CustomerOwnership::isOwner($this->security?->getUser(), $dto, 'customerId') || $this->hasBackendAccess('wishlists')) {
            \Mage::dispatchEvent('api_wishlist_item_dto_build', ['dto' => $dto]);
        }

        return $dto;
    }

    /**
     * @return array<string, mixed>
     */
    private function wishlistDtoToArray(WishlistItem $item): array
    {
        return [
            'id' => $item->id,
            'customerId' => $item->customerId,
            'productId' => $item->productId,
            'productName' => $item->productName,
            'productSku' => $item->productSku,
            'productPrice' => $item->productPrice,
            'productFinalPrice' => $item->productFinalPrice,
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
        $item->customerId = isset($data['customerId']) ? (int) $data['customerId'] : null;
        $item->productId = (int) $data['productId'];
        $item->productName = $data['productName'];
        $item->productSku = $data['productSku'];
        $item->productPrice = isset($data['productPrice']) ? (float) $data['productPrice'] : null;
        $item->productFinalPrice = isset($data['productFinalPrice']) ? (float) $data['productFinalPrice'] : null;
        $item->productImageUrl = $data['productImageUrl'] ?? '';
        $item->productUrl = $data['productUrl'] ?? '';
        $item->productType = $data['productType'] ?? 'simple';
        $item->qty = (int) ($data['qty'] ?? 1);
        $item->description = $data['description'] ?? null;
        $item->addedAt = $data['addedAt'] ?? null;
        $item->inStock = (bool) ($data['inStock'] ?? true);

        \Mage::dispatchEvent('api_wishlist_item_dto_build', ['dto' => $item]);

        return $item;
    }
}
