<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A partial refresh (cron or "Refresh Statistics for the Last Day") clears every period touched by a row
 * updated since $from; each cleared period must be aggregated again in full.
 *
 * @see https://github.com/MahoCommerce/maho/issues/1708
 */
describe('Invoiced report partial refresh', function () {
    beforeEach(function () {
        $this->resource = Mage::getSingleton('core/resource');
        $this->adapter = $this->resource->getConnection('core_write');
        $this->orderTable = $this->resource->getTableName('sales/order');
        $this->aggregatedTable = $this->resource->getTableName('sales/invoiced_aggregated_order');

        // Far from the sample data dates, so the asserted period only holds this order
        $this->createdAt = '2001-03-14 12:00:00';
        $this->period = '2001-03-14';

        $this->adapter->insert($this->orderTable, [
            'store_id' => 1,
            'state' => Mage_Sales_Model_Order::STATE_PROCESSING,
            'status' => 'processing',
            'grand_total' => 100.00,
            'base_grand_total' => 100.00,
            'base_total_invoiced' => 100.00,
            'base_total_paid' => 100.00,
            'base_to_global_rate' => 1,
            'base_to_order_rate' => 1,
            'created_at' => $this->createdAt,
            'updated_at' => $this->createdAt,
        ]);
        $this->orderId = (int) $this->adapter->lastInsertId($this->orderTable);
    });

    afterEach(function () {
        $this->adapter->delete($this->orderTable, ['entity_id = ?' => $this->orderId]);
        $this->adapter->delete($this->aggregatedTable, ['period = ?' => $this->period]);
    });

    it('keeps an old period when one of its orders is updated after the lifetime refresh', function () {
        $report = Mage::getResourceModel('sales/report_invoiced');
        $countOrders = fn() => (int) $this->adapter->fetchOne(
            "SELECT SUM(orders_count) FROM {$this->aggregatedTable} WHERE store_id = 0 AND period = ?",
            [$this->period],
        );

        $report->aggregate();
        expect($countOrders())->toBe(1);

        // The order is updated now (e.g. shipped or invoiced), years after it was created
        $this->adapter->update(
            $this->orderTable,
            ['updated_at' => Mage::app()->getLocale()->nowUtc()],
            ['entity_id = ?' => $this->orderId],
        );

        // Same call as the aggregate_sales_report_invoiced_data cron
        $report->aggregate(Mage::app()->getLocale()->utcToStore()->modify('-25 hours'));

        expect($countOrders())->toBe(1);
    });
});
