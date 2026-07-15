<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Integration tests for the Reports → Sales → Invoiced grid collections.
 *
 * The grid query aliases its aggregates (SUM(orders_count) AS orders_count),
 * and Maho\Db\Select expands column aliases inside HAVING for PostgreSQL
 * compatibility. The HAVING condition must therefore reference the bare
 * alias, otherwise the expansion produces a nested aggregate
 * (SUM(SUM(orders_count))) that every database engine rejects.
 *
 * @see https://github.com/MahoCommerce/maho/issues/1100
 */
describe('Invoiced Report Grid Collection', function () {
    beforeEach(function () {
        $this->resource = Mage::getSingleton('core/resource');
        $this->adapter = $this->resource->getConnection('core_write');
        $this->today = date('Y-m-d');
        $this->yesterday = date('Y-m-d', strtotime('-1 day'));

        // Both "Match Period To" variants read structurally identical tables
        $this->tables = [
            $this->resource->getTableName('sales/invoiced_aggregated_order'),
            $this->resource->getTableName('sales/invoiced_aggregated'),
        ];

        foreach ($this->tables as $table) {
            $this->adapter->delete($table, ['period IN(?)' => [$this->today, $this->yesterday]]);

            // Two statuses on the same day so the day row is a real SUM (2 + 3),
            // plus a second day to verify grouping by period
            foreach ([
                ['period' => $this->today, 'order_status' => 'processing', 'orders_count' => 2],
                ['period' => $this->today, 'order_status' => 'complete', 'orders_count' => 3],
                ['period' => $this->yesterday, 'order_status' => 'processing', 'orders_count' => 1],
            ] as $row) {
                $this->adapter->insert($table, $row + [
                    'store_id' => 1,
                    'orders_invoiced' => 1,
                    'invoiced' => 100.0000,
                    'invoiced_captured' => 60.0000,
                    'invoiced_not_captured' => 40.0000,
                ]);
            }
        }
    });

    afterEach(function () {
        foreach ($this->tables as $table) {
            $this->adapter->delete($table, ['period IN(?)' => [$this->today, $this->yesterday]]);
        }
    });

    it('loads the daily grid collection without SQL errors', function (string $collectionAlias) {
        $collection = Mage::getResourceModel($collectionAlias)
            ->setPeriod('day')
            ->setDateRange($this->yesterday, $this->today)
            ->addStoreFilter([1]);

        $rows = [];
        foreach ($collection as $item) {
            $rows[$item->getData('period')] = (int) $item->getData('orders_count');
        }

        // GROUP BY does not guarantee row order, so compare order-independently
        ksort($rows);
        expect($rows)->toBe([
            $this->yesterday => 1,
            $this->today => 5,
        ]);
    })->with([
        'order variant' => 'sales/report_invoiced_collection_order',
        'invoiced variant' => 'sales/report_invoiced_collection_invoiced',
    ]);

    it('assembles the HAVING clause without nested aggregates', function () {
        $collection = Mage::getResourceModel('sales/report_invoiced_collection_order')
            ->setPeriod('day')
            ->setDateRange($this->yesterday, $this->today)
            ->addStoreFilter([1]);

        $collection->load();
        $sql = (string) $collection->getSelect();

        expect($sql)->toContain('SUM(orders_count) > 0')
            ->and($sql)->not->toContain('SUM(SUM(');
    });

    it('loads the totals collection without SQL errors', function () {
        $collection = Mage::getResourceModel('sales/report_invoiced_collection_order')
            ->setDateRange($this->yesterday, $this->today)
            ->addStoreFilter([1])
            ->setAggregatedColumns(['orders_count' => 'sum(orders_count)']);
        $collection->isTotals(true);
        $collection->load();

        $totals = $collection->getFirstItem();

        expect((int) $totals->getData('orders_count'))->toBe(6);
    });
});
