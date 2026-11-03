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
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Post Provider, extends CrudProvider with blog-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class adds collection filters and the urlKey-based lookup.
 */
final class BlogPostProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['publish_date' => 'DESC'];

    #[\Override]
    protected bool $supportsScopeAll = true;
    #[\Override]
    protected ?string $backendResource = 'blog-posts';

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
                return $this->singleItemPaginator($this->getPostByUrlKey($urlKey));
            }
            return $this->provideCollection($context);
        }

        return $this->provideItem((int) $uriVariables['id']);
    }

    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        /** @var \Maho_Blog_Service_Post $service */
        $service = \Mage::getService('blog/post');
        try {
            $post = $this->isBackendReader() ? $service->getById((int) $id) : $service->getVisibleById((int) $id, StoreContext::getStoreId());
        } catch (\Mage_Core_Exception_NoSuchEntity) {
            return null;
        }
        if ($this->isBackendReader()) {
            $this->assertReadableStores($post->getStores(), 'post');
        }
        return $this->toDto($post);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        if (!$this->isBackendReader()) {
            /** @var \Maho_Blog_Service_Post $service */
            $service = \Mage::getService('blog/post');
            $service->addVisibleFilter($collection, StoreContext::getStoreId());
        } elseif (!$this->isScopeAll($filters)) {
            $collection->addPublishedFilter();
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $like = '%' . trim($search) . '%';
            $collection->addAttributeToFilter([
                ['attribute' => 'title', 'like' => $like],
                ['attribute' => 'content', 'like' => $like],
            ]);
        }

        if (($filters['categoryId'] ?? '') !== '') {
            // Posts belong to many categories through a link table, so filter the link
            // rather than a column on the post.
            $select = $collection->getSelect();
            $alias = (string) array_key_first((array) $select->getPart(\Maho\Db\Select::FROM));
            $select->joinInner(
                ['post_category' => \Mage::getSingleton('core/resource')->getTableName('blog/post_category')],
                "post_category.post_id = {$alias}.entity_id",
                [],
            )->where('post_category.category_id = ?', (int) $filters['categoryId']);
        }
    }

    private function getPostByUrlKey(string $urlKey): ?Resource
    {
        if (!$this->isBackendReader()) {
            $postId = \Mage::getModel('blog/post')->getPostIdByUrlKey($urlKey, StoreContext::getStoreId());
            return $postId ? $this->provideItem($postId) : null;
        }

        // A backend reader finds a post of any store view and status, then the item path checks the stores of the token
        /** @var \Maho_Blog_Model_Resource_Post_Collection $collection */
        $collection = \Mage::getResourceModel('blog/post_collection');
        $collection->addAttributeToFilter('url_key', $urlKey);

        $allowed = $this->allowedStoreIds();
        if ($allowed !== null) {
            $collection->addStoreFilter($allowed, false);
        }

        $collection->setPageSize(1);
        $post = $collection->getFirstItem();

        return $post->getId() ? $this->provideItem((int) $post->getId()) : null;
    }
}
