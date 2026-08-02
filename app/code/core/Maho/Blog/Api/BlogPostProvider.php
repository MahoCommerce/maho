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
    protected array $defaultSort = ['publish_date' => 'DESC'];

    protected bool $supportsScopeAll = true;
    protected ?string $backOfficeResource = 'blog-posts';

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
        $post = \Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            return null;
        }

        if ($this->isBackOfficeReader()) {
            $this->assertReadableStores($post->getStores(), 'post');

            return $this->toDto($post);
        }

        if (!$post->getIsActive()) {
            return null;
        }

        if (!$this->isPublished($post)) {
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

        if (!$this->isScopeAll($filters)) {
            $collection->addFieldToFilter('is_active', 1);

<<<<<<< HEAD
        $collection->addFieldToFilter('publish_date', [
            'or' => [
                ['null' => true],
                ['lteq' => $this->publishedCutoff()],
            ],
        ]);
=======
            $collection->addFieldToFilter('publish_date', [
                'or' => [
                    ['null' => true],
                    ['lteq' => $this->publishedCutoff()],
                ],
            ]);
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
>>>>>>> 46dc60e (Added missing REST/GraphQL API fields, operations, and store-scoped reads/writes across all resources (#1210))
    }

    private function getPostByUrlKey(string $urlKey): ?Resource
    {
        if ($this->isBackOfficeReader()) {
            return $this->getPostByUrlKeyBackOffice($urlKey);
        }

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

        if (!$this->isPublished($post)) {
            return null;
        }

        return $this->toDto($post);
    }

    /**
     * getPostIdByUrlKey() only matches active, current-store posts; back-office
     * readers resolve across every store and status, then reuse the item path
     * so the store-restricted-token check applies.
     */
    private function getPostByUrlKeyBackOffice(string $urlKey): ?Resource
    {
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

    /**
     * A future-scheduled post must not be reachable by direct id or urlKey, so
     * the single-item paths apply the same publish_date cutoff as the collection
     * (applyCollectionFilters): a null publish_date is always live, otherwise it
     * must be at or before the cutoff.
     */
    private function isPublished(object $post): bool
    {
        $publishDate = $post->getPublishDate();
        if (empty($publishDate)) {
            return true;
        }

        return $publishDate <= $this->publishedCutoff();
    }

    /**
     * publish_date is a store-local date (Maho_Blog_Model_Resource_Post::_beforeSave
     * defaults it to today in the store's timezone), so compare it against today in
     * that same timezone — as the frontend blocks and the sitemap observer do. A UTC
     * cutoff would hide a post published today from every store ahead of UTC until
     * UTC caught up.
     */
    private function publishedCutoff(): string
    {
        return \Mage::app()->getLocale()->utcToStore()->format(\Mage_Core_Model_Locale::DATE_FORMAT);
    }
}
