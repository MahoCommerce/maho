<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fix in
 * Mage_SalesRule_Model_Resource_Report_Collection::_getSelectedColumns():
 * the bare 'rule_name' column in the default (non-totals/non-subtotals)
 * branch became 'MAX(rule_name)' so the SELECT list is valid under
 * ONLY_FULL_GROUP_BY (MySQL/MariaDB dev mode) and PostgreSQL's always-on
 * strict GROUP BY, while GROUP BY (period format, coupon_code) is unchanged.
 *
 * Strict-mode enforcement note: MahoBackendTestCase::setUp() opens the DB
 * connection before the test body runs, so on MySQL/MariaDB the assembled
 * collection SQL is executed through a freshly created adapter opened after
 * Mage::setIsDeveloperMode(true) (which applies ONLY_FULL_GROUP_BY in
 * _initConnection()). On PostgreSQL strict GROUP BY is always enforced, so
 * the live adapter suffices. SQLite has no strict GROUP BY mode: skipped.
 */
beforeEach(function () {
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($liveAdapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode; test is MySQL/PostgreSQL only.');
    }
});

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Create a fresh MySQL/MariaDB adapter with ONLY_FULL_GROUP_BY active.
 * On PostgreSQL the live singleton is sufficient (strict mode is always on).
 */
function salesRuleStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($liveAdapter instanceof Mysql) {
        Mage::setIsDeveloperMode(true);
        return new Mysql($liveAdapter->getConfig());
    }

    return $liveAdapter;
}

/**
 * Execute assembled SQL through a strict-mode adapter and return the rows.
 * Throws on any GROUP BY violation (error 1055 on MySQL, 42803 on PostgreSQL),
 * failing the calling test automatically.
 */
function salesRuleRunInStrictMode(\Maho\Db\Select $select): array
{
    return salesRuleStrictAdapter()->fetchAll($select->assemble());
}

/**
 * Insert deterministic aggregate rows into a coupon aggregation table.
 *
 * Layout (all store_id 1; rule_name is constant per coupon_code, matching how
 * the aggregation writer populates the table — which is what makes
 * MAX(rule_name) deterministic):
 *   2026-01-15 / pending  / MAHO-SGB-A: uses 2, discount  5
 *   2026-01-15 / complete / MAHO-SGB-A: uses 3, discount  7
 *   2026-01-15 / pending  / MAHO-SGB-B: uses 1, discount  3
 *   2026-02-10 / pending  / MAHO-SGB-A: uses 4, discount  9
 *
 * Daily grouping (period + coupon_code) must yield 3 rows; the two
 * 2026-01-15 / MAHO-SGB-A source rows must collapse into one group.
 */
function salesRuleInsertAggregateFixture(string $tableAlias): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName($tableAlias);

    salesRuleCleanAggregateFixture($tableAlias);

    $rows = [
        ['2026-01-15', 'pending',  'MAHO-SGB-A', 'Maho Strict Rule A', 2, '10.0000', '5.0000'],
        ['2026-01-15', 'complete', 'MAHO-SGB-A', 'Maho Strict Rule A', 3, '20.0000', '7.0000'],
        ['2026-01-15', 'pending',  'MAHO-SGB-B', 'Maho Strict Rule B', 1, '30.0000', '3.0000'],
        ['2026-02-10', 'pending',  'MAHO-SGB-A', 'Maho Strict Rule A', 4, '40.0000', '9.0000'],
    ];

    foreach ($rows as [$period, $status, $coupon, $ruleName, $uses, $subtotal, $discount]) {
        $adapter->insert($table, [
            'period'                 => $period,
            'store_id'               => 1,
            'order_status'           => $status,
            'coupon_code'            => $coupon,
            'rule_name'              => $ruleName,
            'coupon_uses'            => $uses,
            'subtotal_amount'        => $subtotal,
            'discount_amount'        => $discount,
            'total_amount'           => $subtotal,
            'subtotal_amount_actual' => $subtotal,
            'discount_amount_actual' => $discount,
            'total_amount_actual'    => $subtotal,
        ]);
    }
}

/**
 * Remove fixture rows from a coupon aggregation table.
 */
function salesRuleCleanAggregateFixture(string $tableAlias): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->delete(
        $resource->getTableName($tableAlias),
        ['coupon_code IN (?)' => ['MAHO-SGB-A', 'MAHO-SGB-B']],
    );
}

/**
 * Build and load a fully configured coupon report collection for the fixture
 * data (period mode + store 1 + fixture date range), returning it after load()
 * so getSelect() holds the final assembled query.
 */
function salesRuleLoadReportCollection(string $collectionModel, string $period): Mage_SalesRule_Model_Resource_Report_Collection
{
    /** @var Mage_SalesRule_Model_Resource_Report_Collection $collection */
    $collection = Mage::getResourceModel($collectionModel);
    $collection
        ->setPeriod($period)
        ->setDateRange('2026-01-01', '2026-12-31')
        ->addStoreFilter([1]);
    $collection->load();

    return $collection;
}

/**
 * Keep only the rows created by this file's fixtures. The sample-data install
 * can leave unrelated aggregated rows inside the same store/date range, so
 * absolute row counts must be scoped to the fixture coupon codes.
 */
function salesRuleFixtureRows(array $rows): array
{
    return array_values(array_filter(
        $rows,
        fn(array $row) => in_array($row['coupon_code'] ?? null, ['MAHO-SGB-A', 'MAHO-SGB-B'], true),
    ));
}

/**
 * Find a grouped result row by coupon code and period prefix.
 */
function salesRuleFindRow(array $rows, string $couponCode, string $periodPrefix): ?array
{
    foreach ($rows as $row) {
        if ($row['coupon_code'] === $couponCode && str_starts_with((string) $row['period'], $periodPrefix)) {
            return $row;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Requirement 1: default (daily) branch of _getSelectedColumns()
// ---------------------------------------------------------------------------

it('loads the daily coupon report collection without strict GROUP BY errors and aggregates rule_name per coupon', function () {
    salesRuleInsertAggregateFixture('salesrule/coupon_aggregated');

    try {
        $collection = salesRuleLoadReportCollection('salesrule/report_collection', 'day');

        // Regression anchor: rule_name must be wrapped in an aggregate in the SELECT list.
        expect(strtoupper($collection->getSelect()->assemble()))->toContain('MAX(RULE_NAME)');

        // Executes under strict GROUP BY (throws -> test fails).
        $rows = salesRuleFixtureRows(salesRuleRunInStrictMode($collection->getSelect()));

        // 3 fixture groups: (2026-01-15, A), (2026-01-15, B), (2026-02-10, A).
        expect($rows)->toHaveCount(3);
        $fixtureItems = array_filter(
            $collection->getItems(),
            fn($item) => in_array($item->getData('coupon_code'), ['MAHO-SGB-A', 'MAHO-SGB-B'], true),
        );
        expect($fixtureItems)->toHaveCount(3);

        // The two 2026-01-15 / MAHO-SGB-A source rows collapsed into one group
        // with summed measures and the (constant-per-coupon) rule name intact.
        $rowA = salesRuleFindRow($rows, 'MAHO-SGB-A', '2026-01-15');
        expect($rowA)->not->toBeNull();
        expect((int) $rowA['coupon_uses'])->toBe(5);
        expect((float) $rowA['discount_amount'])->toBe(12.0);
        expect((float) $rowA['subtotal_amount'])->toBe(30.0);
        expect($rowA['rule_name'])->toBe('Maho Strict Rule A');

        $rowB = salesRuleFindRow($rows, 'MAHO-SGB-B', '2026-01-15');
        expect($rowB)->not->toBeNull();
        expect((int) $rowB['coupon_uses'])->toBe(1);
        expect($rowB['rule_name'])->toBe('Maho Strict Rule B');
    } finally {
        salesRuleCleanAggregateFixture('salesrule/coupon_aggregated');
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: month and year period formats (same fixed branch, other
// _periodFormat variants) execute under strict GROUP BY
// ---------------------------------------------------------------------------

it('loads the monthly and yearly coupon report collections without strict GROUP BY errors', function () {
    salesRuleInsertAggregateFixture('salesrule/coupon_aggregated');

    try {
        // Month: fixture groups (2026-01, A), (2026-01, B), (2026-02, A).
        $monthCollection = salesRuleLoadReportCollection('salesrule/report_collection', 'month');
        $monthRows = salesRuleFixtureRows(salesRuleRunInStrictMode($monthCollection->getSelect()));
        expect($monthRows)->toHaveCount(3);

        $monthRowA = salesRuleFindRow($monthRows, 'MAHO-SGB-A', '2026-01');
        expect($monthRowA)->not->toBeNull();
        expect((int) $monthRowA['coupon_uses'])->toBe(5);
        expect($monthRowA['rule_name'])->toBe('Maho Strict Rule A');

        // Year: fixture groups (2026, A) and (2026, B); all three A rows collapse.
        $yearCollection = salesRuleLoadReportCollection('salesrule/report_collection', 'year');
        $yearRows = salesRuleFixtureRows(salesRuleRunInStrictMode($yearCollection->getSelect()));
        expect($yearRows)->toHaveCount(2);

        $yearRowA = salesRuleFindRow($yearRows, 'MAHO-SGB-A', '2026');
        expect($yearRowA)->not->toBeNull();
        expect((int) $yearRowA['coupon_uses'])->toBe(9);
        expect((float) $yearRowA['discount_amount'])->toBe(21.0);
        expect($yearRowA['rule_name'])->toBe('Maho Strict Rule A');
    } finally {
        salesRuleCleanAggregateFixture('salesrule/coupon_aggregated');
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: the rules filter WHERE shape stays valid pre-grouping
// ---------------------------------------------------------------------------
// _applyRulesFilter() adds quoteInto('rule_name = ?', $name) WHERE conditions
// on the bare column. WHERE is evaluated before grouping, so it must remain
// valid alongside the MAX(rule_name) SELECT. The filter is exercised at the
// SQL level (same shape _applyRulesFilter emits) rather than via
// addRuleFilter(), because getUniqRulesNamesList() contains a double-quoted
// empty-string literal ('rule_name <> ""') that PostgreSQL rejects as an
// identifier — a pre-existing issue unrelated to the GROUP BY fix.

it('keeps the rule_name WHERE filter valid alongside the aggregated SELECT under strict GROUP BY', function () {
    salesRuleInsertAggregateFixture('salesrule/coupon_aggregated');

    try {
        $collection = salesRuleLoadReportCollection('salesrule/report_collection', 'day');

        $select = clone $collection->getSelect();
        $select->where($collection->getConnection()->quoteInto('rule_name = ?', 'Maho Strict Rule A'));

        $rows = salesRuleRunInStrictMode($select);

        // Only the two MAHO-SGB-A groups remain.
        expect($rows)->toHaveCount(2);
        foreach ($rows as $row) {
            expect($row['coupon_code'])->toBe('MAHO-SGB-A');
            expect($row['rule_name'])->toBe('Maho Strict Rule A');
        }
    } finally {
        salesRuleCleanAggregateFixture('salesrule/coupon_aggregated');
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: Updatedat collection inherits the fix unchanged
// ---------------------------------------------------------------------------

it('loads the updated-at coupon report collection without strict GROUP BY errors', function () {
    salesRuleInsertAggregateFixture('salesrule/coupon_aggregated_updated');

    try {
        $collection = salesRuleLoadReportCollection('salesrule/report_updatedat_collection', 'day');
        expect($collection)->toBeInstanceOf(Mage_SalesRule_Model_Resource_Report_Updatedat_Collection::class);
        expect(strtoupper($collection->getSelect()->assemble()))->toContain('MAX(RULE_NAME)');

        $rows = salesRuleFixtureRows(salesRuleRunInStrictMode($collection->getSelect()));
        expect($rows)->toHaveCount(3);

        $rowA = salesRuleFindRow($rows, 'MAHO-SGB-A', '2026-01-15');
        expect($rowA)->not->toBeNull();
        expect((int) $rowA['coupon_uses'])->toBe(5);
        expect($rowA['rule_name'])->toBe('Maho Strict Rule A');
    } finally {
        salesRuleCleanAggregateFixture('salesrule/coupon_aggregated_updated');
    }
});

// ---------------------------------------------------------------------------
// Requirement 5: neighboring subtotals branch stays strict-safe
// ---------------------------------------------------------------------------
// Not part of the fix, but shares _getSelectedColumns()/_initSelect(): the
// subtotals branch (aggregated columns + period, GROUP BY period only) must
// also keep executing under strict mode.

it('loads the coupon report collection in subtotals mode without strict GROUP BY errors', function () {
    salesRuleInsertAggregateFixture('salesrule/coupon_aggregated');

    try {
        /** @var Mage_SalesRule_Model_Resource_Report_Collection $collection */
        $collection = Mage::getResourceModel('salesrule/report_collection');
        $collection
            ->setPeriod('day')
            ->setDateRange('2026-01-01', '2026-12-31')
            ->addStoreFilter([1])
            ->setAggregatedColumns([
                'coupon_uses'     => 'SUM(coupon_uses)',
                'discount_amount' => 'SUM(discount_amount)',
            ])
            ->isSubTotals(true);
        $collection->load();

        $rows = salesRuleRunInStrictMode($collection->getSelect());

        // Subtotal rows carry no coupon_code, so assertions are scoped to the
        // two fixture periods instead of counting all rows: 2026-01-15
        // (uses 2+3+1) and 2026-02-10 (uses 4). Sample data may contribute
        // rows for other periods in the same range.
        $usesByPeriod = [];
        foreach ($rows as $row) {
            $usesByPeriod[(string) $row['period']] = (int) $row['coupon_uses'];
        }
        expect($usesByPeriod['2026-01-15'] ?? null)->toBe(6);
        expect($usesByPeriod['2026-02-10'] ?? null)->toBe(4);
    } finally {
        salesRuleCleanAggregateFixture('salesrule/coupon_aggregated');
    }
});
