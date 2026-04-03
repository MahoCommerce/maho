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

namespace Maho\ApiPlatform;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\ApiPlatform\Trait\PaginationTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Base class for all API state providers.
 *
 * Provides a default provide() implementation that handles the common pattern:
 * - Named operation dispatch via handleOperation()
 * - Collection queries with pagination, filtering, and sorting
 * - Single item loading by ID
 *
 * Simple providers only need to set $modelAlias and implement toDto().
 * Complex providers can override provide() entirely.
 *
 * @implements ProviderInterface<object>
 */
abstract class Provider implements ProviderInterface
{
    use AuthenticationTrait;
    use PaginationTrait;

    protected ?string $modelAlias = null;
    protected int $defaultPageSize = 20;
    protected int $maxPageSize = 100;
    /** @var array<string, string> e.g. ['title' => 'ASC'] */
    protected array $defaultSort = [];

    public function __construct(?Security $security = null)
    {
        $this->security = $security;
    }

    /**
     * Default provide() routing: named operations → collection → single item.
     *
     * Override this entirely for resources with non-standard routing.
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $custom = $this->handleOperation($operation->getName(), $context, $uriVariables);
        if ($custom !== null) {
            return $custom;
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($context);
        }

        return $this->provideItem($uriVariables['id'] ?? 0);
    }

    /**
     * Hook for named operations (custom GraphQL queries, etc.).
     * Return null to fall through to default collection/item routing.
     */
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        return null;
    }

    /**
     * Map a Maho model to an API resource DTO.
     *
     * Subclasses that use the default provide() must override this.
     * Subclasses that override provide() entirely can skip this.
     */
    public function toDto(object $model): Resource
    {
        throw new \LogicException(static::class . ' must implement toDto() or override provide()');
    }

    /**
     * Apply custom filters to a collection.
     * Override to add store filters, search, field filters, etc.
     */
    protected function applyCollectionFilters(object $collection, array $filters): void {}

    /**
     * Load a single item by ID. Override for custom loading logic
     * (e.g. store availability checks).
     */
    protected function provideItem(int|string $id): ?Resource
    {
        $model = \Mage::getModel($this->modelAlias)->load($id);
        return $model->getId() ? $this->toDto($model) : null;
    }

    /**
     * Load a paginated collection. Override for custom collection logic
     * (e.g. caching, batch loading).
     *
     * @return TraversablePaginator<Resource>
     */
    protected function provideCollection(array $context): TraversablePaginator
    {
        $collection = \Mage::getModel($this->modelAlias)->getCollection();

        $this->applyCollectionFilters($collection, $context['filters'] ?? []);

        foreach ($this->defaultSort as $field => $dir) {
            $collection->setOrder($field, $dir);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(
            $context,
            $this->defaultPageSize,
            $this->maxPageSize,
        );
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $items = [];
        foreach ($collection as $model) {
            $items[] = $this->toDto($model);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, $total);
    }
}
