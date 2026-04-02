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

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Category State Provider
 */
final class BlogCategoryProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return BlogCategory|TraversablePaginator<BlogCategory>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BlogCategory|TraversablePaginator|null
    {
        StoreContext::ensureStore();

        if ($operation instanceof CollectionOperationInterface) {
            $urlKey = $context['args']['urlKey'] ?? $context['filters']['urlKey'] ?? null;
            if ($urlKey) {
                $category = $this->getCategoryByUrlKey($urlKey);
                $items = $category ? [$category] : [];
                return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
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
        if (!StoreContext::isAvailableForStore($stores, $storeId)) {
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
     * @return TraversablePaginator<BlogCategory>
     */
    private function getCollection(array $context): TraversablePaginator
    {
        $storeId = StoreContext::getStoreId();
        $collection = \Mage::getResourceModel('blog/category_collection');
        $collection->addStoreFilter($storeId);
        $collection->addActiveFilter();
        $collection->setOrder('position', 'ASC');

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 50, 100);
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $categories = [];
        foreach ($collection as $category) {
            $categories[] = $this->mapToDto($category);
        }

        $total = (int) $collection->getSize();

        return new TraversablePaginator(new \ArrayIterator($categories), $page, $pageSize, $total);
    }

    public function mapToDto(\Maho_Blog_Model_Category $category): BlogCategory
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
