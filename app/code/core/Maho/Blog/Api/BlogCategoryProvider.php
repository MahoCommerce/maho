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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Blog Category Provider — extends CrudProvider with category-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class adds store/active filters and the urlKey-based lookup.
 */
final class BlogCategoryProvider extends CrudProvider
{
    protected int $defaultPageSize = 50;
    protected int $maxPageSize = 100;
    protected array $defaultSort = ['position' => 'ASC'];

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
                $category = $this->getCategoryByUrlKey($urlKey);
                $items = $category ? [$category] : [];
                return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
            }
            return $this->provideCollection($context);
        }

        return $this->provideItem((int) $uriVariables['id']);
    }

    #[\Override]
    protected function provideItem(int|string $id): ?Resource
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

        return $this->toDto($category);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addActiveFilter();
    }

    private function getCategoryByUrlKey(string $urlKey): ?Resource
    {
        $storeId = StoreContext::getStoreId();
        $model = \Mage::getModel('blog/category');
        $categoryId = $model->getCategoryIdByUrlKey($urlKey, $storeId);

        if (!$categoryId) {
            return null;
        }

        return $this->provideItem($categoryId);
    }
}
