<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Category State Provider - Fetches category data for API Platform.
 */
final class CategoryProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * Provide category data based on operation type
     *
     * @return TraversablePaginator<Category>|Category|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Category|null
    {
        // Ensure valid store context
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // Handle categoryByUrlKey query
        if ($operationName === 'categoryByUrlKey') {
            $urlKey = $context['args']['urlKey'] ?? null;
            return $urlKey ? $this->getCategoryByUrlKey($urlKey) : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection($context);
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    /**
     * Get a single category by ID
     */
    private function getItem(int $id): ?Category
    {
        $mahoCategory = \Mage::getModel('catalog/category')->load($id);

        // Single-item reads must apply the same is_active + store-tree scoping
        // the collection path applies; otherwise a disabled category, or one
        // belonging to another store's root tree (including its rendered
        // landing_page CMS block), is readable by guessing its id.
        if (!$this->isAccessibleCategory($mahoCategory)) {
            return null;
        }

        return $this->mapToDto($mahoCategory, true);
    }

    /**
     * Get a category by URL key
     */
    private function getCategoryByUrlKey(string $urlKey): ?Category
    {
        $category = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('url_key', $urlKey)
            ->addAttributeToFilter('is_active', 1)
            ->setPageSize(1)
            ->getFirstItem();

        if (!$category->getId()) {
            return null;
        }

        // Load full category
        $mahoCategory = \Mage::getModel('catalog/category')->load($category->getId());

        // url_key is not unique across store trees, so re-scope after reload to
        // avoid returning a same-keyed category from another store's tree.
        if (!$this->isAccessibleCategory($mahoCategory)) {
            return null;
        }

        return $this->mapToDto($mahoCategory, true);
    }

    /**
     * Whether a category is active and lives under the current store's root
     * category tree. Mirrors the scoping the collection path applies so single
     * lookups (by id / url key) cannot leak disabled or cross-store categories.
     */
    private function isAccessibleCategory(\Mage_Catalog_Model_Category $category): bool
    {
        if (!$category->getId() || !$category->getIsActive()) {
            return false;
        }

        $rootCategoryId = (int) StoreContext::getRootCategoryId();
        if ($rootCategoryId <= 0) {
            return true;
        }

        // The store root and every descendant carry the root id in their path
        // ("1/<root>/..."). Anchoring with slashes prevents substring matches.
        $pathIds = array_map('intval', explode('/', (string) $category->getPath()));
        return in_array($rootCategoryId, $pathIds, true);
    }

    /**
     * Get category collection (tree)
     *
     * @return TraversablePaginator<Category>
     */
    private function getCollection(array $context): TraversablePaginator
    {
        $filters = $context['args'] ?? $context['filters'] ?? [];
        $parentId = $filters['parentId'] ?? null;
        $includeInMenu = $filters['includeInMenu'] ?? null;
        $search = $filters['search'] ?? $filters['q'] ?? null;

        // If searching, don't filter by parent - search all categories
        // If no parent specified and not searching, get root category children
        if ($parentId === null && !$search) {
            $parentId = StoreContext::getRootCategoryId();
        }

        $collection = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect(['name', 'url_key', 'url_path', 'image', 'is_active', 'include_in_menu', 'position', 'level', 'description', 'display_mode', 'landing_page', 'page_layout'])
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('position', 'ASC');

        // Filter by parent if specified (or defaulted)
        if ($parentId !== null) {
            $collection->addAttributeToFilter('parent_id', $parentId);
        }

        // Apply search filter on category name
        if ($search) {
            $escapedSearch = addcslashes($search, '%_');
            $collection->addAttributeToFilter('name', ['like' => "%{$escapedSearch}%"]);

            // Search has no parent filter, so without this it would return
            // categories from every store's tree. Constrain to the current
            // store root so cross-store categories aren't leaked via search.
            $rootCategoryId = (int) StoreContext::getRootCategoryId();
            if ($rootCategoryId > 0) {
                $collection->addAttributeToFilter('path', ['like' => "%/{$rootCategoryId}/%"]);
            }
        }

        if ($includeInMenu !== null) {
            $collection->addAttributeToFilter('include_in_menu', (int) $includeInMenu);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 100, 500);

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $categories = [];
        foreach ($collection as $mahoCategory) {
            $categories[] = $this->mapToDto($mahoCategory, false);
        }

        return new TraversablePaginator(new \ArrayIterator($categories), $page, $pageSize, $total);
    }

    /**
     * Map Maho category model to Category DTO
     */
    public function mapToDto(\Mage_Catalog_Model_Category $category, bool $includeChildren = false): Category
    {
        $dto = new Category();
        $dto->id = (int) $category->getId();
        $dto->parentId = $category->getParentId() ? (int) $category->getParentId() : null;
        $dto->name = $category->getName() ?? '';
        $dto->description = $category->getDescription();
        $dto->urlKey = $category->getUrlKey();
        $dto->urlPath = $category->getUrlPath();
        $dto->level = (int) $category->getLevel();
        $dto->position = (int) $category->getPosition();
        $dto->isActive = (bool) $category->getIsActive();
        $dto->includeInMenu = (bool) $category->getIncludeInMenu();
        $dto->path = $category->getPath();
        $dto->displayMode = $category->getDisplayMode() ?: null;
        // Render CMS static block if landing_page is set
        $landingPage = $category->getLandingPage();
        if ($landingPage) {
            $dto->cmsBlock = $this->renderCmsBlock((int) $landingPage);
        }
        $dto->metaTitle = $category->getMetaTitle();
        $dto->metaKeywords = $category->getMetaKeywords();
        $dto->metaDescription = $category->getMetaDescription();
        $dto->pageLayout = $category->getPageLayout() ?: null;
        $dto->createdAt = $category->getCreatedAt();
        $dto->updatedAt = $category->getUpdatedAt();

        // Get image URL
        if ($category->getImage()) {
            $dto->image = $category->getImageUrl();
        }

        // Get product count
        $dto->productCount = (int) $category->getProductCount();

        // Get children IDs
        $childrenIds = $category->getChildren();
        if ($childrenIds) {
            $dto->childrenIds = array_map('intval', explode(',', $childrenIds));
        }

        // Include children categories if requested
        if ($includeChildren && !empty($dto->childrenIds)) {
            $childCollection = \Mage::getModel('catalog/category')
                ->getCollection()
                ->addAttributeToSelect(['name', 'url_key', 'url_path', 'image', 'is_active', 'include_in_menu', 'position', 'level', 'description', 'display_mode', 'landing_page', 'page_layout'])
                ->addAttributeToFilter('entity_id', ['in' => $dto->childrenIds])
                ->addAttributeToFilter('is_active', 1)
                ->setOrder('position', 'ASC');

            foreach ($childCollection as $childCategory) {
                $dto->children[] = $this->mapToDto($childCategory, false);
            }
        }

        \Mage::dispatchEvent('api_category_dto_build', ['category' => $category, 'dto' => $dto]);

        return $dto;
    }

    /**
     * Render a CMS static block by ID, resolving directives
     */
    private function renderCmsBlock(int $blockId): ?string
    {
        try {
            $cmsBlock = \Mage::getModel('cms/block')
                ->setStoreId(\Mage::app()->getStore()->getId())
                ->load($blockId);
            if (!$cmsBlock->getIsActive() || !$cmsBlock->getContent()) {
                return null;
            }
            return \Maho\ApiPlatform\CrudResource::filterContent($cmsBlock->getContent());
        } catch (\Throwable) {
            return null;
        }
    }
}
