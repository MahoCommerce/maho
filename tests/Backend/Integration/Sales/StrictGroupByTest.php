<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression tests for the strict-GROUP-BY fixes in Mage_Sales:
 *
 * 1. Mage_Sales_Model_Api2_Order::_addTaxInfo() — sales/order_tax LEFT JOIN +
 *    GROUP BY main_table.entity_id replaced with correlated scalar subqueries
 *    (ORDER BY tax_id LIMIT 1). No GROUP BY remains, so payment_method and
 *    gift_message.* columns are legal, and tax_name/tax_rate come
 *    deterministically from the same first tax row.
 * 2. Mage_Sales_Model_Resource_Report_Bestsellers::aggregate() /
 *    _aggregateDefault() — constant-per-group product type columns wrapped in
 *    MIN() (matching the existing MIN(product_name) pattern).
 * 3. Mage_Sales_Model_Resource_Report_Bestsellers_Collection::_makeBoundarySelect()
 *    — bare DATE_FORMAT(period, ...) wrapped in MAX() for year/month boundary
 *    selects that group by product_id only (guarded by !isTotals()).
 *
 * Enable developer mode FIRST (before any lazily-opened connection), so that
 * _initConnection() on MySQL/MariaDB applies ONLY_FULL_GROUP_BY. On PostgreSQL
 * strict GROUP BY is always enforced (flag irrelevant). On SQLite there is no
 * strict GROUP BY; tests are skipped.
 *
 * For collection-built queries the assembled SQL is additionally re-executed
 * through a freshly opened strict-mode adapter (see salesGroupByStrictAdapter),
 * so the strict check does not depend on when the shared connection was opened.
 */
beforeEach(function () {
    Mage::setIsDeveloperMode(true);

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode');
    }
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Adapter that is guaranteed to enforce strict GROUP BY.
 * On MySQL/MariaDB a fresh connection is opened after developer mode was
 * enabled, so _initConnection() applies ONLY_FULL_GROUP_BY.
 * On PostgreSQL strict GROUP BY is always on, the live adapter is enough.
 */
function salesGroupByStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($liveAdapter instanceof Mysql) {
        Mage::setIsDeveloperMode(true);
        return new Mysql($liveAdapter->getConfig());
    }

    return $liveAdapter;
}

/**
 * Execute an assembled select through a strict-mode adapter and return the rows.
 * Throws (failing the test) on any GROUP BY violation: error 1055 on MySQL,
 * SQLSTATE 42803 on PostgreSQL.
 */
function salesGroupByRunInStrictMode(\Maho\Db\Select $select): array
{
    return salesGroupByStrictAdapter()->fetchAll($select->assemble());
}

/**
 * First product of the catalog: ['id' => entity_id, 'type_id' => type_id].
 */
function salesGroupByFirstProduct(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $row = $adapter->fetchRow(
        $adapter->select()
            ->from($resource->getTableName('catalog/product'), ['entity_id', 'type_id'])
            ->order('entity_id')
            ->limit(1),
    );
    if (!$row) {
        throw new \RuntimeException('No products found in test database for Sales fixtures.');
    }
    return ['id' => (int) $row['entity_id'], 'type_id' => (string) $row['type_id']];
}

/**
 * Create an order with a gift message, a payment record and two tax rows.
 * Returns ['order_id' => ..., 'gift_message_id' => ...].
 */
function salesGroupByCreateApi2OrderFixture(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('giftmessage/message'), [
        'customer_id' => 0,
        'sender'      => 'Strict Sender',
        'recipient'   => 'Strict Recipient',
        'message'     => 'Strict GROUP BY gift message',
    ]);
    $giftMessageId = (int) $adapter->lastInsertId($resource->getTableName('giftmessage/message'));

    $adapter->insert($resource->getTableName('sales/order'), [
        'store_id'          => 1,
        'state'             => Mage_Sales_Model_Order::STATE_NEW,
        'status'            => 'pending',
        'customer_is_guest' => 1,
        'customer_email'    => 'strict-groupby@example.test',
        'increment_id'      => 'SGB' . time() . random_int(100, 999),
        'gift_message_id'   => $giftMessageId,
        'grand_total'       => 110,
        'base_grand_total'  => 110,
        'created_at'        => '2026-04-01 10:00:00',
        'updated_at'        => '2026-04-01 10:00:00',
    ]);
    $orderId = (int) $adapter->lastInsertId($resource->getTableName('sales/order'));

    $adapter->insert($resource->getTableName('sales/order_payment'), [
        'parent_id' => $orderId,
        'method'    => 'checkmo',
    ]);

    // Two tax rows: the first inserted row gets the lowest tax_id, so both
    // subqueries (ORDER BY tax_id LIMIT 1) must return "First Tax" / 10%.
    $adapter->insert($resource->getTableName('sales/order_tax'), [
        'order_id'         => $orderId,
        'code'             => 'first_tax',
        'title'            => 'First Tax',
        'percent'          => 10,
        'amount'           => 10,
        'priority'         => 0,
        'position'         => 0,
        'base_amount'      => 10,
        'process'          => 0,
        'base_real_amount' => 10,
    ]);
    $adapter->insert($resource->getTableName('sales/order_tax'), [
        'order_id'         => $orderId,
        'code'             => 'second_tax',
        'title'            => 'Second Tax',
        'percent'          => 5,
        'amount'           => 5,
        'priority'         => 1,
        'position'         => 1,
        'base_amount'      => 5,
        'process'          => 0,
        'base_real_amount' => 5,
    ]);

    return ['order_id' => $orderId, 'gift_message_id' => $giftMessageId];
}

/**
 * Remove the Api2 order fixture (payment and tax rows cascade with the order).
 */
function salesGroupByCleanApi2OrderFixture(array $fixture): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->delete($resource->getTableName('sales/order'), ['entity_id = ?' => $fixture['order_id']]);
    $adapter->delete($resource->getTableName('giftmessage/message'), ['gift_message_id = ?' => $fixture['gift_message_id']]);
}

/**
 * Api2 order resource exposing the protected _add*Info() select modifiers
 * with tax name/rate columns force-allowed (bypasses the ACL attribute filter,
 * which needs a full Api2 request context not available in backend tests).
 */
function salesGroupByApi2OrderResource(): Mage_Sales_Model_Api2_Order
{
    return new class extends Mage_Sales_Model_Api2_Order {
        #[\Override]
        public function _isTaxNameAllowed()
        {
            return true;
        }

        #[\Override]
        public function _isTaxRateAllowed()
        {
            return true;
        }

        public function addAllInfoForTest(Mage_Sales_Model_Resource_Order_Collection $collection): void
        {
            $this->_addPaymentMethodInfo($collection);
            $this->_addGiftMessageInfo($collection);
            $this->_addTaxInfo($collection);
        }

        public function addTaxInfoForTest(Mage_Sales_Model_Resource_Order_Collection $collection): void
        {
            $this->_addTaxInfo($collection);
        }
    };
}

/**
 * Insert a minimal guest order (no payment, no gift message) with the given
 * tax rows ([title, percent] pairs, inserted in order so the first pair gets
 * the lowest tax_id). Returns the order entity_id.
 */
function salesGroupByCreateBareOrder(array $taxRows = []): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('sales/order'), [
        'store_id'          => 1,
        'state'             => Mage_Sales_Model_Order::STATE_NEW,
        'status'            => 'pending',
        'customer_is_guest' => 1,
        'customer_email'    => 'strict-groupby-list@example.test',
        'increment_id'      => 'SGBL' . uniqid(),
        'grand_total'       => 100,
        'base_grand_total'  => 100,
        'created_at'        => '2026-04-02 10:00:00',
        'updated_at'        => '2026-04-02 10:00:00',
    ]);
    $orderId = (int) $adapter->lastInsertId($resource->getTableName('sales/order'));

    foreach ($taxRows as $position => [$title, $percent]) {
        $adapter->insert($resource->getTableName('sales/order_tax'), [
            'order_id'         => $orderId,
            'code'             => 'sgb_tax_' . $position,
            'title'            => $title,
            'percent'          => $percent,
            'amount'           => 1,
            'priority'         => $position,
            'position'         => $position,
            'base_amount'      => 1,
            'process'          => 0,
            'base_real_amount' => 1,
        ]);
    }

    return $orderId;
}

/**
 * Insert bestsellers daily rows (store 0, the default store filter of the
 * report collection) for the given product. $rows = [period => qty, ...].
 */
function salesGroupByInsertBestsellerDailyRows(int $productId, array $rows): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('sales/bestsellers_aggregated_daily');

    foreach ($rows as $period => $qty) {
        $adapter->delete($table, [
            'period = ?'     => $period,
            'store_id = ?'   => 0,
            'product_id = ?' => $productId,
        ]);
        $adapter->insert($table, [
            'period'          => $period,
            'store_id'        => 0,
            'product_id'      => $productId,
            'product_type_id' => 'simple',
            'product_name'    => 'Strict GroupBy Bestseller',
            'product_price'   => 10,
            'qty_ordered'     => $qty,
            'rating_pos'      => 1,
        ]);
    }
}

/**
 * Delete bestsellers daily fixture rows.
 */
function salesGroupByCleanBestsellerDailyRows(int $productId, array $periods): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete($resource->getTableName('sales/bestsellers_aggregated_daily'), [
        'product_id = ?' => $productId,
        'store_id = ?'   => 0,
        'period IN (?)'  => $periods,
    ]);
}

// ---------------------------------------------------------------------------
// Requirement 1: Api2 Order — tax/payment/gift message info without GROUP BY
// ---------------------------------------------------------------------------

it('builds the Api2 order select with payment, gift message, and tax info without GROUP BY and with deterministic tax data', function () {
    $fixture = salesGroupByCreateApi2OrderFixture();

    try {
        /** @var Mage_Sales_Model_Resource_Order_Collection $collection */
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('entity_id', $fixture['order_id']);
        salesGroupByApi2OrderResource()->addAllInfoForTest($collection);

        // The fix removed the GROUP BY entirely — plain joins + correlated subqueries.
        $sql = $collection->getSelect()->assemble();
        expect(strtoupper($sql))->not->toContain('GROUP BY');

        // Executes under strict GROUP BY (fresh ONLY_FULL_GROUP_BY adapter on
        // MySQL/MariaDB, native strictness on PostgreSQL); throws = test fails.
        $rows = salesGroupByRunInStrictMode($collection->getSelect());
        expect($rows)->toHaveCount(1);

        $row = $rows[0];
        expect($row['payment_method'])->toBe('checkmo');
        expect($row['gift_message_from'])->toBe('Strict Sender');
        expect($row['gift_message_to'])->toBe('Strict Recipient');
        expect($row['gift_message_body'])->toBe('Strict GROUP BY gift message');

        // Both subqueries order by tax_id, so tax_name AND tax_rate must come
        // from the same, first tax row (the old grouped join was arbitrary).
        expect($row['tax_name'])->toBe('First Tax');
        expect((float) $row['tax_rate'])->toEqual(10.0);
    } finally {
        salesGroupByCleanApi2OrderFixture($fixture);
    }
});

it('loads and counts the Api2 order collection through the regular collection path', function () {
    $fixture = salesGroupByCreateApi2OrderFixture();

    try {
        /** @var Mage_Sales_Model_Resource_Order_Collection $collection */
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('entity_id', $fixture['order_id']);
        salesGroupByApi2OrderResource()->addAllInfoForTest($collection);

        // With no GROUP BY left, getSelectCountSql() needs no grouped wrapping.
        expect($collection->getSize())->toBe(1);

        $order = $collection->getFirstItem();
        expect((int) $order->getId())->toBe($fixture['order_id']);
        expect($order->getPaymentMethod())->toBe('checkmo');
        expect($order->getTaxName())->toBe('First Tax');
        expect((float) $order->getTaxRate())->toEqual(10.0);
    } finally {
        salesGroupByCleanApi2OrderFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: Bestsellers aggregation (aggregate + _aggregateDefault)
// ---------------------------------------------------------------------------

it('aggregates the bestsellers report without strict GROUP BY errors and preserves the product type', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $product = salesGroupByFirstProduct();

    // Order placed 2026-05-15 (a range no sample data touches).
    $adapter->insert($resource->getTableName('sales/order'), [
        'store_id'          => 1,
        'state'             => Mage_Sales_Model_Order::STATE_COMPLETE,
        'status'            => 'complete',
        'customer_is_guest' => 1,
        'customer_email'    => 'strict-groupby-bestsellers@example.test',
        'increment_id'      => 'SGB' . time() . random_int(100, 999),
        'grand_total'       => 30,
        'base_grand_total'  => 30,
        'created_at'        => '2026-05-15 12:00:00',
        'updated_at'        => '2026-05-15 12:00:00',
    ]);
    $orderId = (int) $adapter->lastInsertId($resource->getTableName('sales/order'));

    $adapter->insert($resource->getTableName('sales/order_item'), [
        'order_id'       => $orderId,
        'store_id'       => 1,
        'product_id'     => $product['id'],
        'product_type'   => $product['type_id'],
        'sku'            => 'sgb-bestseller-test',
        'name'           => 'Strict GroupBy Bestseller Item',
        'qty_ordered'    => 3,
        'price'          => 10,
        'base_price'     => 10,
        'row_total'      => 30,
        'base_row_total' => 30,
    ]);

    try {
        // Runs both fixed insert-from-select queries: aggregate() with
        // MIN(product.type_id) and _aggregateDefault() with MIN(product_type_id).
        Mage::getResourceModel('sales/report_bestsellers')->aggregate('2026-05-14', '2026-05-16');

        $dailyTable = $resource->getTableName('sales/bestsellers_aggregated_daily');
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($dailyTable)
                ->where('product_id = ?', $product['id'])
                ->where('period >= ?', '2026-05-14')
                ->where('period <= ?', '2026-05-16'),
        );

        $byStore = [];
        foreach ($rows as $row) {
            $byStore[(int) $row['store_id']] = $row;
        }

        // Per-store row from aggregate()
        expect($byStore)->toHaveKey(1);
        expect((float) $byStore[1]['qty_ordered'])->toEqual(3.0);
        expect($byStore[1]['product_type_id'])->toBe($product['type_id']);

        // Default-store row from _aggregateDefault()
        expect($byStore)->toHaveKey(0);
        expect((float) $byStore[0]['qty_ordered'])->toEqual(3.0);
        expect($byStore[0]['product_type_id'])->toBe($product['type_id']);
    } finally {
        // Order items / payments / taxes cascade with the order.
        $adapter->delete($resource->getTableName('sales/order'), ['entity_id = ?' => $orderId]);
        $adapter->delete($resource->getTableName('sales/bestsellers_aggregated_daily'), [
            'product_id = ?' => $product['id'],
            'period >= ?'    => '2026-05-14',
            'period <= ?'    => '2026-05-16',
        ]);
        $adapter->delete($resource->getTableName('sales/bestsellers_aggregated_monthly'), [
            'product_id = ?' => $product['id'],
            'period = ?'     => '2026-05-01',
        ]);
        $adapter->delete($resource->getTableName('sales/bestsellers_aggregated_yearly'), [
            'product_id = ?' => $product['id'],
            'period = ?'     => '2026-01-01',
        ]);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: Bestsellers collection boundary selects (month/year periods)
// ---------------------------------------------------------------------------

it('loads the monthly bestsellers collection with a mid-month boundary range without strict GROUP BY errors', function () {
    $product = salesGroupByFirstProduct();
    $periods = ['2026-03-10' => 2, '2026-03-15' => 3];
    salesGroupByInsertBestsellerDailyRows($product['id'], $periods);

    try {
        // Same month, from/to not on month boundaries: _beforeLoad() builds a
        // union with _makeBoundarySelect(), which groups by product_id only —
        // the fixed path wraps the period expression in MAX().
        /** @var Mage_Sales_Model_Resource_Report_Bestsellers_Collection $collection */
        $collection = Mage::getResourceModel('sales/report_bestsellers_collection');
        $collection->setPeriod('month')->setDateRange('2026-03-05', '2026-03-20');
        $collection->load();

        $item = null;
        foreach ($collection as $candidate) {
            if ((int) $candidate->getProductId() === $product['id']) {
                $item = $candidate;
                break;
            }
        }
        expect($item)->not->toBeNull();
        expect((string) $item->getPeriod())->toBe('2026-03');
        expect((float) $item->getQtyOrdered())->toEqual(5.0);

        // Re-execute the assembled union under a guaranteed-strict adapter and
        // check the boundary rows keep the correct period label and summed qty.
        $matched = null;
        foreach (salesGroupByRunInStrictMode($collection->getSelect()) as $row) {
            if ((int) $row['product_id'] === $product['id']) {
                $matched = $row;
                break;
            }
        }
        expect($matched)->not->toBeNull();
        expect((string) $matched['period'])->toBe('2026-03');
        expect((float) $matched['qty_ordered'])->toEqual(5.0);
    } finally {
        salesGroupByCleanBestsellerDailyRows($product['id'], array_keys($periods));
    }
});

it('loads the yearly bestsellers collection with a mid-year boundary range without strict GROUP BY errors', function () {
    $product = salesGroupByFirstProduct();
    $periods = ['2026-03-10' => 2, '2026-03-15' => 3];
    salesGroupByInsertBestsellerDailyRows($product['id'], $periods);

    try {
        // Same year, from/to not on year boundaries: exercises the 'year'
        // branch of the fixed _makeBoundarySelect() (MAX-wrapped %Y label).
        /** @var Mage_Sales_Model_Resource_Report_Bestsellers_Collection $collection */
        $collection = Mage::getResourceModel('sales/report_bestsellers_collection');
        $collection->setPeriod('year')->setDateRange('2026-03-05', '2026-03-20');
        $collection->load();

        $item = null;
        foreach ($collection as $candidate) {
            if ((int) $candidate->getProductId() === $product['id']) {
                $item = $candidate;
                break;
            }
        }
        expect($item)->not->toBeNull();
        expect((string) $item->getPeriod())->toBe('2026');
        expect((float) $item->getQtyOrdered())->toEqual(5.0);

        $matched = null;
        foreach (salesGroupByRunInStrictMode($collection->getSelect()) as $row) {
            if ((int) $row['product_id'] === $product['id']) {
                $matched = $row;
                break;
            }
        }
        expect($matched)->not->toBeNull();
        expect((string) $matched['period'])->toBe('2026');
        expect((float) $matched['qty_ordered'])->toEqual(5.0);
    } finally {
        salesGroupByCleanBestsellerDailyRows($product['id'], array_keys($periods));
    }
});

it('keeps the bestsellers totals mode working with a boundary range (totals column set is untouched by the fix)', function () {
    $product = salesGroupByFirstProduct();
    $periods = ['2026-03-10' => 2, '2026-03-15' => 3];
    salesGroupByInsertBestsellerDailyRows($product['id'], $periods);

    try {
        // isTotals() guards the fix: the aggregated column set must pass through
        // _makeBoundarySelect() unchanged and still load correctly.
        /** @var Mage_Sales_Model_Resource_Report_Bestsellers_Collection $collection */
        $collection = Mage::getResourceModel('sales/report_bestsellers_collection');
        $collection->isTotals(true);
        $collection->setAggregatedColumns(['qty_ordered' => 'SUM(qty_ordered)']);
        $collection->setPeriod('month')->setDateRange('2026-03-05', '2026-03-20');
        $collection->load();

        $rows = salesGroupByRunInStrictMode($collection->getSelect());
        expect($rows)->toHaveCount(1);
        expect((float) $rows[0]['qty_ordered'])->toBeGreaterThanOrEqual(5.0);
    } finally {
        salesGroupByCleanBestsellerDailyRows($product['id'], array_keys($periods));
    }
});
