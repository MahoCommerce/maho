<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Page Provider — extends CrudProvider with page-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class only adds collection filters and identifier-based lookups.
 */
final class CmsPageProvider extends CrudProvider
{
    protected int $defaultPageSize = 100;
    protected int $maxPageSize = 100;
    protected array $defaultSort = ['title' => 'ASC'];

    /**
     * Override provide() to handle identifier-based collection filtering
     * that returns a single-item paginator.
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $this->resourceClass = $operation->getClass();
        if (is_subclass_of($this->resourceClass, \Maho\ApiPlatform\CrudResource::class)) {
            $this->modelAlias = $this->resourceClass::metadata()->model;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $identifier = $context['args']['identifier'] ?? $context['filters']['identifier'] ?? null;
            if ($identifier) {
                $page = $this->getPageByIdentifier($identifier);
                $items = $page ? [$page] : [];
                return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
            }
        }

        return parent::provide($operation, $uriVariables, $context);
    }

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'cmsPagesByIdentifier') {
            $identifier = $context['args']['identifier'] ?? null;
            if (!$identifier) {
                return new TraversablePaginator(new \ArrayIterator([]), 1, 1, 0);
            }

            $page = $this->getPageByIdentifier($identifier);
            $items = $page ? [$page] : [];
            return new TraversablePaginator(new \ArrayIterator($items), 1, 1, count($items));
        }

        return null;
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addFieldToFilter('is_active', 1);

        if (!empty($filters['identifier'])) {
            $collection->addFieldToFilter('identifier', $filters['identifier']);
        }

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search && mb_strlen($search) >= 3) {
            $collection->addFieldToFilter(
                ['title', 'content', 'identifier'],
                [
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                ],
            );
        }
    }

    private function getPageByIdentifier(string $identifier): ?CmsPage
    {
        $storeId = StoreContext::getStoreId();
        $page = \Mage::getModel('cms/page');

        $pageId = $page->checkIdentifier($identifier, $storeId);

        if (!$pageId) {
            return null;
        }

        $page->load($pageId);

        if (!$page->getId() || !$page->getIsActive()) {
            return null;
        }

        /** @var CmsPage */
        return $this->toDto($page);
    }
}
