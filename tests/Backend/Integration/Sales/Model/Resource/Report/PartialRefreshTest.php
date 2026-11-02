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
describe('Sales report partial refresh', function () {
    beforeEach(function () {
        $this->resource = Mage::getSingleton('core/resource');
        $this->adapter = $this->resource->getConnection('core_write');
        $this->orderTable = $this->resource->getTableName('sales/order');

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
            'total_refunded' => 100.00,
            'base_total_refunded' => 100.00,
            'is_virtual' => 0,
            'shipping_description' => 'Flat Rate - Fixed',
            'base_to_global_rate' => 1,
            'base_to_order_rate' => 1,
            'created_at' => $this->createdAt,
            'updated_at' => $this->createdAt,
        ]);
        $this->orderId = (int) $this->adapter->lastInsertId($this->orderTable);
    });

    afterEach(function () {
        $this->adapter->delete($this->orderTable, ['entity_id = ?' => $this->orderId]);
        foreach (['sales/invoiced_aggregated_order', 'sales/refunded_aggregated_order', 'sales/shipping_aggregated_order'] as $alias) {
            $this->adapter->delete($this->resource->getTableName($alias), ['period = ?' => $this->period]);
        }
    });

    it('keeps an old period when one of its orders is updated after the lifetime refresh', function (string $model, string $aggregatedAlias) {
        $aggregatedTable = $this->resource->getTableName($aggregatedAlias);
        $report = Mage::getResourceModel($model);
        $countOrders = fn() => (int) $this->adapter->fetchOne(
            "SELECT SUM(orders_count) FROM {$aggregatedTable} WHERE store_id = 0 AND period = ?",
            [$this->period],
        );

        $report->aggregate();
        expect($countOrders())->toBe(1);

        // The order is updated now, for example when it ships, years after it was created
        $this->adapter->update(
            $this->orderTable,
            ['updated_at' => Mage::app()->getLocale()->nowUtc()],
            ['entity_id = ?' => $this->orderId],
        );

        // Same call as the aggregate_sales_report_invoiced_data cron
        $report->aggregate(Mage::app()->getLocale()->utcToStore()->modify('-25 hours'));

        expect($countOrders())->toBe(1);
    })->with([
        'invoiced' => ['sales/report_invoiced', 'sales/invoiced_aggregated_order'],
        'refunded' => ['sales/report_refunded', 'sales/refunded_aggregated_order'],
        'shipping' => ['sales/report_shipping', 'sales/shipping_aggregated_order'],
    ]);
});

describe('Partial refresh of a period that starts before $from', function () {
    afterEach(function () {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = Mage::getSingleton('core/resource');
        foreach ($this->orderIds ?? [] as $orderId) {
            $adapter->delete($resource->getTableName('sales/order_item'), ['order_id = ?' => $orderId]);
            $adapter->delete($resource->getTableName('sales/order'), ['entity_id = ?' => $orderId]);
        }
        $adapter->delete($resource->getTableName('sales/order_aggregated_created'), ['period = ?' => '2001-03-14']);
        $adapter->delete($resource->getTableName('reports/viewed_aggregated_daily'), ['period = ?' => '2001-03-14']);
        $adapter->delete($resource->getTableName('reports/viewed_aggregated_monthly'), ['period = ?' => '2001-03-01']);
        $adapter->delete($resource->getTableName('reports/viewed_aggregated_yearly'), ['period = ?' => '2001-01-01']);
        $adapter->delete($resource->getTableName('reports/event'), ['logged_at >= ?' => '2001-03-14 00:00:00', 'logged_at < ?' => '2001-03-15 00:00:00']);
    });

    it('counts the orders of the whole day in the orders report', function () {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $this->orderIds = [];
        foreach (['2001-03-14 06:00:00', '2001-03-14 18:00:00'] as $createdAt) {
            $adapter->insert($resource->getTableName('sales/order'), [
                'store_id' => 1,
                'state' => Mage_Sales_Model_Order::STATE_PROCESSING,
                'status' => 'processing',
                'grand_total' => 100.00,
                'base_grand_total' => 100.00,
                'base_to_global_rate' => 1,
                'base_to_order_rate' => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $orderId = (int) $adapter->lastInsertId($resource->getTableName('sales/order'));
            $this->orderIds[] = $orderId;
            $adapter->insert($resource->getTableName('sales/order_item'), [
                'order_id' => $orderId,
                'product_id' => 1,
                'product_type' => 'simple',
                'sku' => 'partial-refresh-' . $orderId,
                'name' => 'Partial refresh product',
                'qty_ordered' => 1,
                'price' => 90,
                'base_price' => 90,
                'row_total' => 90,
                'base_row_total' => 90,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        Mage::getResourceModel('sales/report_order_createdat')->aggregate('2001-03-14 12:00:00', '2001-03-14 23:59:59');

        expect((int) $adapter->fetchOne(
            'SELECT SUM(orders_count) FROM ' . $resource->getTableName('sales/order_aggregated_created') . ' WHERE store_id = 0 AND period = ?',
            ['2001-03-14'],
        ))->toBe(2);
    });

    it('counts the views of the whole day in the product views report', function () {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $productId = (int) $adapter->fetchOne('SELECT MIN(entity_id) FROM ' . $resource->getTableName('catalog/product'));
        foreach (['2001-03-14 06:00:00', '2001-03-14 18:00:00'] as $loggedAt) {
            $adapter->insert($resource->getTableName('reports/event'), [
                'logged_at' => $loggedAt,
                'event_type_id' => Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW,
                'object_id' => $productId,
                'subject_id' => 0,
                'subtype' => 0,
                'store_id' => 1,
            ]);
        }

        Mage::getResourceModel('reports/report_product_viewed')->aggregate('2001-03-14 12:00:00', '2001-03-14 23:59:59');

        expect((int) $adapter->fetchOne(
            'SELECT SUM(views_num) FROM ' . $resource->getTableName('reports/viewed_aggregated_daily') . ' WHERE store_id = 1 AND period = ?',
            ['2001-03-14'],
        ))->toBe(2);
    });
});
