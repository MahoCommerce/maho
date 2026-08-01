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
 * Blog Category Provider, extends CrudProvider with category-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class adds store/active filters and the urlKey-based lookup.
 */
final class BlogCategoryProvider extends CrudProvider
{
    protected array $defaultSort = ['position' => 'ASC'];

    protected bool $supportsScopeAll = true;

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
                return $this->singleItemPaginator($this->getCategoryByUrlKey($urlKey));
            }
            return $this->provideCollection($context);
        }

        return $this->provideItem((int) $uriVariables['id']);
    }

    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        $category = \Mage::getModel('blog/category')->load($id);

        if (!$category->getId()) {
            return null;
        }

        if ($this->isBackOfficeReader()) {
            $this->assertReadableStores($category->getStores(), 'category');

            return $this->toDto($category);
        }

        if (!$category->getIsActive()) {
            return null;
        }

        $storeId = StoreContext::getStoreId();
        $stores = $category->getStores();
        if (!StoreContext::isAvailableForStore($stores, $storeId)) {
            return null;
        }

        return $this->toDto($category);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        if (!$this->isScopeAll($filters)) {
            $collection->addActiveFilter();
        }
    }

    private function getCategoryByUrlKey(string $urlKey): ?Resource
    {
        if ($this->isBackOfficeReader()) {
            return $this->getCategoryByUrlKeyBackOffice($urlKey);
        }

        $storeId = StoreContext::getStoreId();
        $model = \Mage::getModel('blog/category');
        $categoryId = $model->getCategoryIdByUrlKey($urlKey, $storeId);

        if (!$categoryId) {
            return null;
        }

        return $this->provideItem($categoryId);
    }

    /**
     * getCategoryIdByUrlKey() only matches active, current-store categories;
     * back-office readers resolve across every store and status, then reuse
     * the item path so the store-restricted-token check applies.
     */
    private function getCategoryByUrlKeyBackOffice(string $urlKey): ?Resource
    {
        /** @var \Maho_Blog_Model_Resource_Category_Collection $collection */
        $collection = \Mage::getResourceModel('blog/category_collection');
        $collection->addAttributeToFilter('url_key', $urlKey);

        $allowed = $this->allowedStoreIds();
        if ($allowed !== null) {
            $collection->addStoreFilter($allowed, false);
        }

        $collection->setPageSize(1);
        $category = $collection->getFirstItem();

        return $category->getId() ? $this->provideItem((int) $category->getId()) : null;
    }
}
