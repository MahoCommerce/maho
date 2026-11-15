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
use Maho\ApiPlatform\Service\CustomAttributes;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Category State Provider - Fetches category data for API Platform.
 */
final class CategoryProvider extends \Maho\ApiPlatform\Provider
{
    private ?bool $backendCategoriesAccess = null;

    /** Admin and API tokens with category access also see inactive categories, like the admin category tree. */
    private function backendCategoriesAccess(): bool
    {
        return $this->backendCategoriesAccess ??= $this->hasBackendAccess('categories');
    }

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
        try {
            $mahoCategory = $this->service()->getById($id, StoreContext::getStoreId());
        } catch (\Mage_Core_Exception_NoSuchEntity) {
            return null;
        }

        // Single-item reads must apply the same is_active + store-tree scoping
        // the collection path applies; otherwise a disabled category, or one
        // belonging to another store's root tree (including its rendered
        // landing_page CMS block), is readable by guessing its id.
        if ($checkVisibility && !$this->isAccessibleCategory($mahoCategory)) {
            return null;
        }

        return $this->mapToDto($mahoCategory, true, withStoreOverrides: true);
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
            ->addAttributeToFilter('url_key', $urlKey);
        if (!$this->backendCategoriesAccess()) {
            $collection->addAttributeToFilter('is_active', 1);
        }

        $this->filterToRootTree($collection);

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
     * Whether the caller may read a category: a guest or a customer reads what the storefront of the current store
     * shows. A backend caller also reads inactive categories, in the trees that scope=all gives it, as the admin
     * category tree shows them.
     */
    private function isAccessibleCategory(\Mage_Catalog_Model_Category $category): bool
    {
        if (!$this->backendCategoriesAccess()) {
            return $this->service()->isVisibleInStore($category, StoreContext::getStoreId());
        }
        return $category->getId() && $this->service()->isInTrees($category, $this->readableRootIds());
    }

    /**
     * Keep only the categories under the root category of the current store. In the
     * admin (global) scope, which only backend callers can request, the root is the
     * tree root: it starts every path ("1/<store root>/..."), so the filter matches
     * that prefix and keeps the categories of all store roots. With $rootIds, keep the trees
     * of those roots instead.
     *
     * @param list<int>|null $rootIds
     */
    private function filterToRootTree(\Mage_Catalog_Model_Resource_Category_Collection $collection, ?array $rootIds = null): void
    {
        if ($rootIds === null) {
            $rootCategoryId = (int) StoreContext::getRootCategoryId();
            if ($rootCategoryId <= 0) {
                return;
            }
            $rootIds = [$rootCategoryId];
        }
        $collection->addAttributeToFilter(array_map(static fn(int $rootId): array => [
            'attribute' => 'path',
            'like' => $rootId === \Mage_Catalog_Model_Category::TREE_ROOT_ID ? "{$rootId}/%" : "%/{$rootId}/%",
        ], array_values(array_unique($rootIds))));
    }

    /**
     * Whether a backend caller asked for the categories of every root tree (?scope=all), as the
     * admin category tree shows them. Guests and customers are refused.
     */
    private function isScopeAll(array $filters): bool
    {
        if (($filters['scope'] ?? null) !== 'all') {
            return false;
        }
        if (!$this->backendCategoriesAccess()) {
            $this->requireUser();
            throw new \Mage_Core_Exception_Forbidden('scope=all requires a backend token');
        }

        return true;
    }

    /**
     * The root categories whose trees the caller may read: the tree root for an unrestricted
     * caller, and the roots of its own store views for a store-restricted token.
     *
     * @return list<int>
     */
    private function readableRootIds(): array
    {
        $rootIds = $this->service()->getRootIds($this->requireUser()->getAllowedStoreIds()) ?? [\Mage_Catalog_Model_Category::TREE_ROOT_ID];

        // A token without a store view reads no tree: -1 matches no path.
        return $rootIds === [] ? [-1] : $rootIds;
    }

    private function service(): \Mage_Catalog_Service_Category
    {
        /** @var \Mage_Catalog_Service_Category */
        return \Mage::getService('catalog/category');
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
        $allTrees = $this->isScopeAll($filters);

        // If searching, don't filter by parent - search all categories
        // If no parent specified and not searching, get root category children
        if ($parentId === null && !$search && !$allTrees) {
            $parentId = StoreContext::getRootCategoryId();
        }

        $collection = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect(['name', 'url_key', 'url_path', 'image', 'is_active', 'is_anchor', 'include_in_menu', 'position', 'level', 'description', 'display_mode', 'landing_page', 'page_layout', 'available_sort_by', 'default_sort_by', 'meta_robots', 'filter_price_range', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'custom_use_parent_settings', 'custom_apply_to_products'])
            ->setOrder('position', 'ASC');
        if (!$this->backendCategoriesAccess()) {
            $collection->addAttributeToFilter('is_active', 1);
        }

        // Filter by parent if specified (or defaulted)
        if ($parentId !== null) {
            $collection->addAttributeToFilter('parent_id', $parentId);
        }

        // Apply search filter on category name
        if ($search) {
            $escapedSearch = addcslashes($search, '%_');
            $collection->addAttributeToFilter('name', ['like' => "%{$escapedSearch}%"]);
        }

        // Always constrain to the current store's root tree, or with scope=all to the trees
        // the caller may read. A client-supplied parentId would otherwise return another
        // store's active categories (including their rendered landing_page CMS blocks);
        // the search path needs it too since it applies no parent filter at all.
        $this->filterToRootTree($collection, $allTrees ? $this->readableRootIds() : null);

        if ($includeInMenu !== null) {
            $collection->addAttributeToFilter('include_in_menu', (int) $includeInMenu);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 100, 500);

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();
        $children = $this->preloadCountsAndChildIds($collection);

        $categories = [];
        foreach ($collection as $mahoCategory) {
            $categories[] = $this->mapToDto($mahoCategory, false, childrenIds: $children[$mahoCategory->getId()] ?? null);
        }

        return new TraversablePaginator(new \ArrayIterator($categories), $page, $pageSize, $total);
    }

    /**
     * Map Maho category model to Category DTO. $withStoreOverrides fills storeOverrides
     * for backend callers; single-category reads and write responses set it.
     *
     * @param list<int>|null $childrenIds Preloaded active children, or null to query them.
     */
    public function mapToDto(\Mage_Catalog_Model_Category $category, bool $includeChildren = false, bool $withStoreOverrides = false, ?array $childrenIds = null): Category
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

        // Design and layout internals are backend data: the layout update is
        // executable markup and the theme/design assignment leaks the storefront's
        // internals. Category reads are public, so only admin tokens and API
        // tokens actually granted a categories permission see them.
        if ($this->backendCategoriesAccess()) {
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
        $dto->attributeSetId = $category->getAttributeSetId() ?: null;

        // Get image URL
        $dto->image = $category->getImageUrl() ?: null;

        // Get product count
        $dto->productCount = (int) $category->getProductCount();

        // Get children IDs
        if ($childrenIds === null) {
            $childrenList = $category->getChildren();
            $childrenIds = $childrenList ? array_map(intval(...), explode(',', $childrenList)) : [];
        }
        $dto->childrenIds = $childrenIds;

        // Include children categories if requested
        if ($includeChildren && !empty($dto->childrenIds)) {
            $childCollection = \Mage::getModel('catalog/category')
                ->getCollection()
                ->addAttributeToSelect(['name', 'url_key', 'url_path', 'image', 'is_active', 'is_anchor', 'include_in_menu', 'position', 'level', 'description', 'display_mode', 'landing_page', 'page_layout', 'available_sort_by', 'default_sort_by', 'meta_robots', 'filter_price_range', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'custom_use_parent_settings', 'custom_apply_to_products'])
                ->addAttributeToFilter('entity_id', ['in' => $dto->childrenIds])
                ->setOrder('position', 'ASC');
            if (!$this->backendCategoriesAccess()) {
                $childCollection->addAttributeToFilter('is_active', 1);
            }

            $childIdsByCategory = $this->preloadCountsAndChildIds($childCollection);
            foreach ($childCollection as $childCategory) {
                $dto->children[] = $this->mapToDto($childCategory, false, childrenIds: $childIdsByCategory[$childCategory->getId()] ?? null);
            }
        }

        if ($withStoreOverrides && $this->backendCategoriesAccess()) {
            $dto->storeOverrides = StoreOverrides::forModel($category);
            $dto->customAttributes = CustomAttributes::read(
                $category,
                array_filter($category->getAttributes(), fn($attribute) => $attribute->getIsUserDefined()),
            );
        }

        \Mage::dispatchEvent('api_category_dto_build', ['category' => $category, 'dto' => $dto]);

        return $dto;
    }

    /**
     * Set product_count on each item and return the active child IDs for each category ID.
     * Return null when a model, resource or collection is rewritten: those keep their own getters.
     *
     * @return array<int, list<int>>|null
     */
    private function preloadCountsAndChildIds(\Mage_Catalog_Model_Resource_Category_Collection $collection): ?array
    {
        $resource = \Mage::getResourceSingleton('catalog/category');
        if ($collection::class !== \Mage_Catalog_Model_Resource_Category_Collection::class
            || $collection->getResource()::class !== \Mage_Catalog_Model_Resource_Category::class
            || $resource::class !== \Mage_Catalog_Model_Resource_Category::class
        ) {
            return null;
        }

        $items = $collection->getItems();
        foreach ($items as $category) {
            if ($category::class !== \Mage_Catalog_Model_Category::class
                || $category->getResource()::class !== \Mage_Catalog_Model_Resource_Category::class
            ) {
                return null;
            }
        }

        $this->loadProductCounts($collection);
        return $resource->getChildrenForCategories($items);
    }

    /**
     * Load direct product assignments, matching Category::getProductCount() for every store and anchor.
     */
    private function loadProductCounts(\Mage_Catalog_Model_Resource_Category_Collection $collection): void
    {
        $items = $collection->getItems();
        if ($items === []) {
            return;
        }

        $connection = $collection->getConnection();
        $select = $connection->select()
            ->from($collection->getTable('catalog/category_product'), [
                'category_id',
                'product_count' => new \Maho\Db\Expr('COUNT(product_id)'),
            ])
            ->where('category_id IN (?)', array_keys($items))
            ->group('category_id');
        $counts = $connection->fetchPairs($select);

        foreach ($items as $category) {
            if (!$category->hasProductCount()) {
                $category->setData('product_count', (int) ($counts[$category->getId()] ?? 0));
            }
        }
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
