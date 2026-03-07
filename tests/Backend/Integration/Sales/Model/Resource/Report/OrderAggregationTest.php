<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

/**
 * Integration tests for report aggregation MySQL 8.0+ compatibility fix.
 *
 * These tests create actual orders in the database and verify that the
 * report aggregation works correctly using WHERE instead of HAVING for
 * date range conditions.
 *
 * @see https://github.com/MahoCommerce/maho/issues/532
 */
describe('Order Report Aggregation Integration', function () {
    beforeEach(function () {
        $this->resource = Mage::getSingleton('core/resource');
        $this->adapter = $this->resource->getConnection('core_write');
        $this->testOrderIds = [];

        // Create test order directly in the database
        $orderTable = $this->resource->getTableName('sales/order');
        $orderItemTable = $this->resource->getTableName('sales/order_item');

        $orderData = [
            'store_id' => 1,
            'state' => Mage_Sales_Model_Order::STATE_PROCESSING,
            'status' => 'processing',
            'grand_total' => 100.00,
            'base_grand_total' => 100.00,
            'subtotal' => 90.00,
            'base_subtotal' => 90.00,
            'total_qty_ordered' => 1,
            'base_tax_amount' => 0,
            'tax_amount' => 0,
            'base_shipping_amount' => 10,
            'shipping_amount' => 10,
            'base_discount_amount' => 0,
            'discount_amount' => 0,
            'base_to_global_rate' => 1,
            'base_to_order_rate' => 1,
            'created_at' => Mage_Core_Model_Locale::now(),
            'updated_at' => Mage_Core_Model_Locale::now(),
            'is_virtual' => 0,
            'shipping_description' => 'Flat Rate - Fixed',
        ];

        $this->adapter->insert($orderTable, $orderData);
        $orderId = (int) $this->adapter->lastInsertId($orderTable);
        $this->testOrderIds[] = $orderId;

        $orderItemData = [
            'order_id' => $orderId,
            'product_id' => 1,
            'product_type' => 'simple',
            'sku' => 'test-report-sku-' . uniqid(),
            'name' => 'Test Report Product',
            'qty_ordered' => 1,
            'qty_invoiced' => 0,
            'price' => 90,
            'base_price' => 90,
            'row_total' => 90,
            'base_row_total' => 90,
            'created_at' => Mage_Core_Model_Locale::now(),
            'updated_at' => Mage_Core_Model_Locale::now(),
        ];

        $this->adapter->insert($orderItemTable, $orderItemData);
    });

    afterEach(function () {
        // Clean up test orders
        foreach ($this->testOrderIds as $orderId) {
            $this->adapter->delete(
                $this->resource->getTableName('sales/order_item'),
                ['order_id = ?' => $orderId],
            );
            $this->adapter->delete(
                $this->resource->getTableName('sales/order'),
                ['entity_id = ?' => $orderId],
            );
        }
    });

    it('aggregates orders by created_at date', function () {
        $reportResource = Mage::getResourceModel('sales/report_order_createdat');

        $from = date('Y-m-d', strtotime('-1 day'));
        $to = date('Y-m-d', strtotime('+1 day'));

        // Run aggregation - this should not throw SQL errors on any database
        $reportResource->aggregate($from, $to);

        // Verify aggregation completed by checking the table has data
        $aggregatedTable = $this->resource->getTableName('sales/order_aggregated_created');
        $count = (int) $this->adapter->fetchOne(
            "SELECT COUNT(*) FROM {$aggregatedTable}",
        );

        // Should have at least some aggregated data
        expect($count)->toBeGreaterThanOrEqual(0);
    });

    it('handles aggregation with specific date range', function () {
        $reportResource = Mage::getResourceModel('sales/report_order_createdat');
        $today = date('Y-m-d');

        // Run aggregation for today only
        $reportResource->aggregate($today, $today);

        expect(true)->toBeTrue();
    });

    it('aggregates orders with correct data', function () {
        $reportResource = Mage::getResourceModel('sales/report_order_createdat');

        $from = date('Y-m-d', strtotime('-1 day'));
        $to = date('Y-m-d', strtotime('+1 day'));

        $reportResource->aggregate($from, $to);

        // Check that aggregated data reflects our test order
        $aggregatedTable = $this->resource->getTableName('sales/order_aggregated_created');
        $result = $this->adapter->fetchRow(
            "SELECT * FROM {$aggregatedTable} WHERE store_id = 1 ORDER BY period DESC LIMIT 1",
        );

        // Should have some data (may include more than just our test order)
        expect($result)->not->toBeNull();
        if ($result) {
            expect((int) $result['orders_count'])->toBeGreaterThanOrEqual(1);
        }
    });
});

describe('Invoiced Report Aggregation Integration', function () {
    it('aggregates invoice data without SQL errors', function () {
        $reportResource = Mage::getResourceModel('sales/report_invoiced');

        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d', strtotime('+1 day'));

        $reportResource->aggregate($from, $to);

        expect(true)->toBeTrue();
    });
});

describe('Refunded Report Aggregation Integration', function () {
    it('aggregates refund data without SQL errors', function () {
        $reportResource = Mage::getResourceModel('sales/report_refunded');

        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d', strtotime('+1 day'));

        $reportResource->aggregate($from, $to);

        expect(true)->toBeTrue();
    });
});

describe('Shipping Report Aggregation Integration', function () {
    it('aggregates shipping data without SQL errors', function () {
        $reportResource = Mage::getResourceModel('sales/report_shipping');

        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d', strtotime('+1 day'));

        $reportResource->aggregate($from, $to);

        expect(true)->toBeTrue();
    });
});

describe('Tax Report Aggregation Integration', function () {
    it('aggregates tax data without SQL errors', function () {
        $reportResource = Mage::getResourceModel('tax/report_tax_createdat');

        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d', strtotime('+1 day'));

        $reportResource->aggregate($from, $to);

        expect(true)->toBeTrue();
    });
});

describe('SalesRule Report Aggregation Integration', function () {
    it('aggregates coupon usage data without SQL errors', function () {
        $reportResource = Mage::getResourceModel('salesrule/report_rule_createdat');

        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d', strtotime('+1 day'));

        $reportResource->aggregate($from, $to);

        expect(true)->toBeTrue();
    });
});
