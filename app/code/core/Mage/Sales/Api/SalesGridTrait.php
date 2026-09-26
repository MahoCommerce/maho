<?php

/**
 * List invoices, shipments and credit memos with the filters of their admin grids.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use Maho\ApiPlatform\Trait\DateRangeFilterTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait SalesGridTrait
{
    use DateRangeFilterTrait;

    /**
     * Load one page of documents of all orders, newest first, in the stores of the token.
     * The grid table gives the order number and the name that the admin grid shows.
     * The models get them as the data keys `order_increment_id` and $nameColumn.
     *
     * Filters: search, orderId, createdFrom, createdTo, and state when $states is not empty.
     *
     * @template T of \Mage_Sales_Model_Abstract
     * @param class-string<T> $modelClass The model class of the collection
     * @param array<string, int> $states State values by name
     * @return array{models: list<T>, total: int, page: int, pageSize: int}
     */
    protected function loadGridPage(string $modelClass, string $collectionAlias, string $gridTable, string $nameColumn, array $states, array $context): array
    {
        $filters = $context['filters'] ?? [];
        $collection = \Mage::getResourceModel($collectionAlias);
        $collection->getSelect()->joinLeft(
            ['grid' => $collection->getTable($gridTable)],
            'grid.entity_id = main_table.entity_id',
            ['order_increment_id', $nameColumn],
        );
        $this->applyAllowedStoreFilter($collection, $this->requireUser());

        $orderId = $this->intFilter($filters, 'orderId');
        if ($orderId !== null) {
            $collection->addFieldToFilter('main_table.order_id', $orderId);
        }

        $state = $this->stringFilter($filters, 'state');
        if ($state !== null && $states !== []) {
            if (!isset($states[$state])) {
                throw new BadRequestHttpException('state must be one of: ' . implode(', ', array_keys($states)));
            }
            $collection->addFieldToFilter('main_table.state', $states[$state]);
        }

        $this->applyDateRangeFilters($collection, $filters, 'main_table.created_at', null);

        // Every word must match part of the document number, the order number or the name
        $adapter = $collection->getConnection();
        foreach ($this->searchWords($this->stringFilter($filters, 'search')) as $word) {
            $like = '%' . $word . '%';
            $collection->getSelect()->where(implode(' OR ', array_map(
                fn(string $column) => $adapter->prepareSqlCondition($column, ['like' => $like]),
                ['main_table.increment_id', 'grid.order_increment_id', "grid.{$nameColumn}"],
            )), null, \Maho\Db\Select::TYPE_CONDITION);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context);
        $collection->getSelect()->order(['main_table.created_at DESC', 'main_table.entity_id DESC']);
        $collection->setPageSize($pageSize)->setCurPage($page);

        return [
            'models' => array_values(array_filter($collection->getItems(), fn($model) => $model instanceof $modelClass)),
            'total' => (int) $collection->getSize(),
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
}
