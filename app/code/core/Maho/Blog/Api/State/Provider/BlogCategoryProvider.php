<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Blog\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\Blog\Api\Resource\BlogCategory;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Category State Provider
 *
 * @implements ProviderInterface<BlogCategory>
 */
final class BlogCategoryProvider implements ProviderInterface
{
    /**
     * @return BlogCategory|ArrayPaginator<BlogCategory>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BlogCategory|ArrayPaginator|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            $urlKey = $context['args']['urlKey'] ?? $context['filters']['urlKey'] ?? null;
            if ($urlKey) {
                $category = $this->getCategoryByUrlKey($urlKey);
                $items = $category ? [$category] : [];
                return new ArrayPaginator(items: $items, currentPage: 1, itemsPerPage: 1, totalItems: count($items));
            }
            return $this->getCollection($context);
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    private function getItem(int $id): ?BlogCategory
    {
        $category = \Mage::getModel('blog/category')->load($id);

        if (!$category->getId() || !$category->getIsActive()) {
            return null;
        }

        $storeId = StoreContext::getStoreId();
        $stores = $category->getStores();
        if (!in_array(0, $stores) && !in_array($storeId, $stores)) {
            return null;
        }

        return $this->mapToDto($category);
    }

    private function getCategoryByUrlKey(string $urlKey): ?BlogCategory
    {
        $storeId = StoreContext::getStoreId();
        $model = \Mage::getModel('blog/category');
        $categoryId = $model->getCategoryIdByUrlKey($urlKey, $storeId);

        if (!$categoryId) {
            return null;
        }

        return $this->getItem($categoryId);
    }

    /**
     * @return ArrayPaginator<BlogCategory>
     */
    private function getCollection(array $context): ArrayPaginator
    {
        $storeId = StoreContext::getStoreId();
        $filters = $context['filters'] ?? [];

        $collection = \Mage::getResourceModel('blog/category_collection');
        $collection->addStoreFilter($storeId);
        $collection->addActiveFilter();
        $collection->setOrder('position', 'ASC');

        $page = (int) ($filters['page'] ?? 1);
        $pageSize = max(1, min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 50), 100));
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $categories = [];
        foreach ($collection as $category) {
            $categories[] = $this->mapToDto($category);
        }

        $total = (int) $collection->getSize();

        return new ArrayPaginator(
            items: $categories,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: $total,
        );
    }

    private function mapToDto(\Maho_Blog_Model_Category $category): BlogCategory
    {
        $dto = new BlogCategory();
        $dto->id = (int) $category->getId();
        $dto->name = (string) $category->getName();
        $dto->urlKey = (string) $category->getUrlKey();
        $dto->parentId = $category->getParentId() ? (int) $category->getParentId() : null;
        $dto->path = $category->getPath();
        $dto->level = (int) $category->getLevel();
        $dto->position = (int) $category->getPosition();
        $dto->isActive = (bool) $category->getIsActive();
        $dto->metaTitle = $category->getMetaTitle();
        $dto->metaDescription = $category->getMetaDescription();
        $dto->metaKeywords = $category->getMetaKeywords();

        \Mage::dispatchEvent('api_blog_category_dto_build', ['category' => $category, 'dto' => $dto]);

        return $dto;
    }
}
