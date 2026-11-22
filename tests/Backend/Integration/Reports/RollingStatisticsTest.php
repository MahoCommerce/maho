<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The rolling tables (issue #1624) hold the units, the revenue and the views of each product in the last days
 * that catalog/frontend/sort_days sets. The refresh of the bestsellers and the viewed statistics rebuilds them.
 */
beforeEach(function (): void {
    $resource = Mage::getSingleton('core/resource');
    $this->db = $resource->getConnection('core_write');
    $this->db->beginTransaction();
    $this->tables = [
        'product' => $resource->getTableName('catalog/product'),
        'order' => $resource->getTableName('sales/order'),
        'item' => $resource->getTableName('sales/order_item'),
        'event' => $resource->getTableName('reports/event'),
        'sales' => $resource->getTableName('sales/bestsellers_aggregated_rolling'),
        'views' => $resource->getTableName('reports/viewed_aggregated_rolling'),
    ];
    $productType = Mage::getSingleton('eav/config')->getEntityType('catalog_product');
    $first = 1 + (int) $this->db->fetchOne($this->db->select()
        ->from($this->tables['product'], new Maho\Db\Expr('MAX(entity_id)')));
    $this->ids = [];
    foreach ([0, 1] as $offset) {
        $this->ids[$offset] = $first + $offset;
        $this->db->insert($this->tables['product'], [
            'entity_id' => $first + $offset, 'entity_type_id' => $productType->getId(),
            'attribute_set_id' => $productType->getDefaultAttributeSetId(),
            'type_id' => 'simple', 'sku' => 'rolling-' . $first . '-' . $offset,
        ]);
    }
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
});

afterEach(function (): void {
    $this->db->rollBack();
});

function rollingOrder(string $state, string $daysAgo, array $items): void
{
    $resource = Mage::getSingleton('core/resource');
    $db = $resource->getConnection('core_write');
    $db->insert($resource->getTableName('sales/order'), [
        'state' => $state,
        'store_id' => (int) Mage::app()->getDefaultStoreView()->getId(),
        'created_at' => Mage::app()->getLocale()->formatDateForDb("-$daysAgo days"),
    ]);
    $orderId = (int) $db->lastInsertId($resource->getTableName('sales/order'));
    foreach ($items as [$productId, $qty, $total, $discount]) {
        $db->insert($resource->getTableName('sales/order_item'), [
            'order_id' => $orderId, 'product_id' => $productId, 'qty_ordered' => $qty,
            'base_row_total' => $total, 'base_discount_amount' => $discount,
        ]);
    }
}

/**
 * @return array<string, array{qty_ordered?: float, revenue?: float, views_num?: int}> keyed by "product-store"
 */
function rollingRows(string $table, array $ids): array
{
    $db = Mage::getSingleton('core/resource')->getConnection('core_read');
    $rows = $db->fetchAll($db->select()->from($table)->where('product_id IN (?)', $ids));
    $result = [];
    foreach ($rows as $row) {
        $key = $row['product_id'] . '-' . $row['store_id'];
        unset($row['product_id'], $row['store_id']);
        $result[$key] = array_map(floatval(...), $row);
    }

    return $result;
}

function rollingRefresh(string $code): void
{
    $statistics = Mage::getModel('reports/statistics');
    $statistics->withAdminStore(static fn() => $statistics->aggregate($code));
}

it('counts the units and the revenue of the orders of the last days, without canceled orders', function () {
    [$a, $b] = $this->ids;
    rollingOrder('new', '1', [[$a, 2, 100, 10], [$b, 1, 20, 0]]);
    rollingOrder('processing', '10', [[$a, 1, 50, 5]]);
    rollingOrder('new', '40', [[$a, 5, 500, 0]]);
    rollingOrder('canceled', '2', [[$a, 7, 700, 0]]);

    rollingRefresh('bestsellers');

    expect(rollingRows($this->tables['sales'], $this->ids))->toEqual([
        "$a-{$this->storeId}" => ['qty_ordered' => 3.0, 'revenue' => 135.0],
        "$b-{$this->storeId}" => ['qty_ordered' => 1.0, 'revenue' => 20.0],
        "$a-0" => ['qty_ordered' => 3.0, 'revenue' => 135.0],
        "$b-0" => ['qty_ordered' => 1.0, 'revenue' => 20.0],
    ]);
});

it('moves the window of the orders with the number of days', function () {
    [$a] = $this->ids;
    rollingOrder('new', '1', [[$a, 2, 100, 0]]);
    rollingOrder('new', '40', [[$a, 5, 500, 0]]);
    // The refresh runs in the admin store
    Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)->setConfig('catalog/frontend/sort_days', '60');

    rollingRefresh('bestsellers');

    expect(rollingRows($this->tables['sales'], $this->ids)["$a-0"])->toEqual(['qty_ordered' => 7.0, 'revenue' => 600.0]);
});

it('counts the views of the last days', function () {
    [$a, $b] = $this->ids;
    $logged = static fn(string $daysAgo): string => Mage::app()->getLocale()->formatDateForDb("-$daysAgo days");
    foreach ([[$a, '1'], [$a, '3'], [$b, '5'], [$a, '40']] as [$productId, $daysAgo]) {
        $this->db->insert($this->tables['event'], [
            'logged_at' => $logged($daysAgo), 'event_type_id' => Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW,
            'object_id' => $productId, 'subject_id' => 0, 'subtype' => 0, 'store_id' => $this->storeId,
        ]);
    }

    rollingRefresh('viewed');

    expect(rollingRows($this->tables['views'], $this->ids))->toEqual([
        "$a-{$this->storeId}" => ['views_num' => 2.0],
        "$b-{$this->storeId}" => ['views_num' => 1.0],
        "$a-0" => ['views_num' => 2.0],
        "$b-0" => ['views_num' => 1.0],
    ]);
});

it('empties the rolling table of a product that has no order in the window any more', function () {
    [$a] = $this->ids;
    $this->db->insert($this->tables['sales'], ['product_id' => $a, 'store_id' => 0, 'qty_ordered' => 4, 'revenue' => 40]);

    rollingRefresh('bestsellers');

    expect(rollingRows($this->tables['sales'], $this->ids))->toBe([]);
});
