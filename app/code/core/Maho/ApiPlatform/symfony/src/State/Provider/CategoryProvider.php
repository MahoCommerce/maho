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

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\Category;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Category State Provider - Fetches category data for API Platform
 *
 * @implements ProviderInterface<Category>
 */
final class CategoryProvider implements ProviderInterface
{
    /**
     * Provide category data based on operation type
     *
     * @return ArrayPaginator<Category>|Category|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|Category|null
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

        if (!$mahoCategory->getId()) {
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

        return $this->mapToDto($mahoCategory, true);
    }

    /**
     * Get category collection (tree)
     *
     * @return ArrayPaginator<Category>
     */
    private function getCollection(array $context): ArrayPaginator
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
            $collection->addAttributeToFilter('name', ['like' => "%{$search}%"]);
        }

        if ($includeInMenu !== null) {
            $collection->addAttributeToFilter('include_in_menu', (int) $includeInMenu);
        }

        $categories = [];
        foreach ($collection as $mahoCategory) {
            $categories[] = $this->mapToDto($mahoCategory, false);
        }

        $total = count($categories);

        return new ArrayPaginator(
            items: $categories,
            currentPage: 1,
            itemsPerPage: max($total, 100),
            totalItems: $total,
        );
    }

    /**
     * Map Maho category model to Category DTO
     */
    private function mapToDto(\Mage_Catalog_Model_Category $category, bool $includeChildren = false): Category
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
            $dto->image = \Mage::getBaseUrl('media') . 'catalog/category/' . $category->getImage();
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

        return $dto;
    }

    /**
     * Render a CMS static block by ID, resolving directives without session dependency
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
            return $this->processDirectives($cmsBlock->getContent());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Process CMS directives ({{block}}, {{media}}, {{config}}) without the full rendering pipeline
     */
    private function processDirectives(string $content): string
    {
        // Resolve {{block type="cms/block" block_id="..."}} by loading referenced blocks recursively
        $content = (string) preg_replace_callback(
            '/\{\{block\s+type="cms\/block"\s+block_id="([^"]+)"\s*\}\}/i',
            function (array $matches): string {
                $identifier = $matches[1];
                try {
                    $block = \Mage::getModel('cms/block')
                        ->setStoreId(\Mage::app()->getStore()->getId())
                        ->load($identifier, 'identifier');
                    if ($block->getIsActive() && $block->getContent()) {
                        return $this->processDirectives($block->getContent());
                    }
                } catch (\Throwable) {
                }
                return '';
            },
            $content,
        );

        // Resolve {{media url="..."}}
        $mediaUrl = \Mage::getBaseUrl('media');
        $content = (string) preg_replace(
            '/\{\{media\s+url="([^"]+)"\s*\}\}/i',
            $mediaUrl . '$1',
            $content,
        );

        // Resolve {{config path="..."}}
        $content = (string) preg_replace_callback(
            '/\{\{config\s+path="([^"]+)"\s*\}\}/i',
            fn(array $m): string => (string) \Mage::getStoreConfig($m[1]),
            $content,
        );

        return $content;
    }
}
