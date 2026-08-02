<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Put;
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


        if ($operation instanceof CollectionOperationInterface) {
            $urlKey = $context['args']['urlKey'] ?? null;
            if ($urlKey) {
                return $this->singleItemPaginator($this->getCategoryByUrlKey($urlKey));
            }
            return $this->getCollection($context);
        }

        // Writes read current state through this provider before the processor
        // runs; authorization (403) lives in the processor, so the visibility
        // gate must not turn that into a 404 here.
        $checkVisibility = !$operation instanceof Put && !$operation instanceof DeleteOperationInterface;
        return $this->getItem((int) $uriVariables['id'], $checkVisibility);
    }

    /**
     * Get a single category by ID
     */
    private function getItem(int $id, bool $checkVisibility = true): ?Category
    {
        $mahoCategory = \Mage::getModel('catalog/category')->load($id);

        if (!$mahoCategory->getId()) {
            return null;
        }

        // Single-item reads must apply the same is_active + store-tree scoping
        // the collection path applies; otherwise a disabled category, or one
        // belonging to another store's root tree (including its rendered
        // landing_page CMS block), is readable by guessing its id.
        if ($checkVisibility && !$this->isAccessibleCategory($mahoCategory)) {
            return null;
        }

        return $this->mapToDto($mahoCategory, true);
    }

    /**
     * Get a category by URL key
     */
    private function getCategoryByUrlKey(string $urlKey): ?Category
    {
        // url_key is not unique across store trees. Constrain the lookup to the
        // current store's root tree so we resolve THIS store's category, rather
        // than picking the first global match (a lower-id category from another
        // store) and then rejecting it — which returned null even when the
        // current store had a valid category with that key.
        $collection = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('url_key', $urlKey)
            ->addAttributeToFilter('is_active', 1);

        $rootCategoryId = (int) StoreContext::getRootCategoryId();
        if ($rootCategoryId > 0) {
            $collection->addAttributeToFilter('path', ['like' => "%/{$rootCategoryId}/%"]);
        }

        $category = $collection->setPageSize(1)->getFirstItem();

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
            ->addAttributeToSelect(['name', 'url_key', 'url_path', 'image', 'is_active', 'is_anchor', 'include_in_menu', 'position', 'level', 'description', 'display_mode', 'landing_page', 'page_layout', 'available_sort_by', 'default_sort_by', 'meta_robots', 'filter_price_range', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'custom_use_parent_settings', 'custom_apply_to_products'])
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
        }

        // Always constrain to the current store's root tree. A client-supplied
        // parentId would otherwise return another store's active categories
        // (including their rendered landing_page CMS blocks); the search path
        // needs it too since it applies no parent filter at all.
        $rootCategoryId = (int) StoreContext::getRootCategoryId();
        if ($rootCategoryId > 0) {
            $collection->addAttributeToFilter('path', ['like' => "%/{$rootCategoryId}/%"]);
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
        $dto->isAnchor = (bool) $category->getIsAnchor();
        $dto->path = $category->getPath();
        $dto->displayMode = $category->getDisplayMode() ?: null;
        // Render CMS static block if landing_page is set
        $landingPage = $category->getLandingPage();
        if ($landingPage) {
            $dto->landingPageId = (int) $landingPage;
            $dto->cmsBlock = $this->renderCmsBlock((int) $landingPage);
        }
        $dto->metaTitle = $category->getMetaTitle();
        $dto->metaKeywords = $category->getMetaKeywords();
        $dto->metaDescription = $category->getMetaDescription();
        $dto->pageLayout = $category->getPageLayout() ?: null;
        $dto->metaRobots = $category->getData('meta_robots') ?: null;

        $availableSortBy = $category->getData('available_sort_by');
        if (is_string($availableSortBy) && $availableSortBy !== '') {
            $availableSortBy = explode(',', $availableSortBy);
        }
        $dto->availableSortBy = is_array($availableSortBy) ? array_values($availableSortBy) : [];
        $dto->defaultSortBy = $category->getData('default_sort_by') ?: null;

        // Design and layout internals are back-office data: the layout update is
        // executable markup and the theme/design assignment leaks the storefront's
        // internals. Category reads are public, so only admin tokens and API
        // tokens actually granted a categories permission see them.
        if ($this->isAdmin() || ($this->isApiUser()
            && ($this->getAuthorizedUser()->hasPermission('categories/read')
                || $this->getAuthorizedUser()->hasPermission('categories/write')))
        ) {
            $dto->customDesign = $category->getData('custom_design') ?: null;
            $customDesignFrom = $category->getData('custom_design_from');
            $dto->customDesignFrom = $customDesignFrom ? substr((string) $customDesignFrom, 0, 10) : null;
            $customDesignTo = $category->getData('custom_design_to');
            $dto->customDesignTo = $customDesignTo ? substr((string) $customDesignTo, 0, 10) : null;
            $dto->customLayoutUpdate = $category->getData('custom_layout_update') ?: null;
        }
        $customUseParent = $category->getData('custom_use_parent_settings');
        $dto->customUseParentSettings = $customUseParent === null ? null : (bool) $customUseParent;
        $customApply = $category->getData('custom_apply_to_products');
        $dto->customApplyToProducts = $customApply === null ? null : (bool) $customApply;
        $filterPriceRange = $category->getData('filter_price_range');
        $dto->filterPriceRange = $filterPriceRange !== null && $filterPriceRange !== '' ? (float) $filterPriceRange : null;

        $dto->childrenCount = (int) $category->getData('children_count');
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
                ->addAttributeToSelect(['name', 'url_key', 'url_path', 'image', 'is_active', 'is_anchor', 'include_in_menu', 'position', 'level', 'description', 'display_mode', 'landing_page', 'page_layout', 'available_sort_by', 'default_sort_by', 'meta_robots', 'filter_price_range', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'custom_use_parent_settings', 'custom_apply_to_products'])
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
