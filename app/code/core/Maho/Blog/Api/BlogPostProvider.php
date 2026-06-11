<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Post Provider — extends CrudProvider with blog-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class adds collection filters and the urlKey-based lookup.
 */
final class BlogPostProvider extends CrudProvider
{
    protected array $defaultSort = ['publish_date' => 'DESC'];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $this->resourceClass = $operation->getClass();
        if (is_subclass_of($this->resourceClass, \Maho\ApiPlatform\CrudResource::class)) {
            $this->modelAlias = $this->resourceClass::metadata()->model;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $urlKey = $context['args']['urlKey'] ?? $context['filters']['urlKey'] ?? null;
            if ($urlKey) {
                $post = $this->getPostByUrlKey($urlKey);
                $items = $post ? [$post] : [];
                return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
            }
            return $this->provideCollection($context);
        }

        return $this->provideItem((int) $uriVariables['id']);
    }

    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        $post = \Mage::getModel('blog/post')->load($id);

        if (!$post->getId() || !$post->getIsActive()) {
            return null;
        }

        $storeId = StoreContext::getStoreId();
        $stores = $post->getStores();
        if (!StoreContext::isAvailableForStore($stores, $storeId)) {
            return null;
        }

        return $this->toDto($post);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addFieldToFilter('is_active', 1);

        $collection->addFieldToFilter('publish_date', [
            'or' => [
                ['null' => true],
                ['lteq' => \Mage::app()->getLocale()->formatDateForDb('now')],
            ],
        ]);
    }

    private function getPostByUrlKey(string $urlKey): ?Resource
    {
        $storeId = StoreContext::getStoreId();
        $post = \Mage::getModel('blog/post');
        $postId = $post->getPostIdByUrlKey($urlKey, $storeId);

        if (!$postId) {
            return null;
        }

        $post->load($postId);

        if (!$post->getId() || !$post->getIsActive()) {
            return null;
        }

        return $this->toDto($post);
    }
}
