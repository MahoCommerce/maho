<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fix in
 * Mage_CatalogRule_Model_Action_Index_Refresh::_prepareIndexSelect():
 * the derived-table columns latest_start_date / earliest_end_date must be
 * aggregate-wrapped (MAX / MIN) or MySQL with ONLY_FULL_GROUP_BY raises
 * error 1055 as soon as a product/customer-group pair has more than one
 * applicable rule row.
 *
 * The whole index query is MySQL-family-only by construction: it relies on
 * session user variables (@price, @group_id, @action_stop) for the running
 * price calculation, so it cannot execute on PostgreSQL or SQLite at all.
 * Tests therefore skip on those adapters. MariaDB runs the tests without
 * forcing ONLY_FULL_GROUP_BY (mirroring the production exemption in
 * Maho\Db\Adapter\Pdo\Mysql::_initConnection, see MDEV-11588) but still
 * validates execution and the aggregated-date semantics.
 *
 * Because MySQL temporary tables are per-connection, everything (temp table
 * creation, seeding, SET @vars, and the SELECT itself) must run on the same
 * live core_write connection — a fresh strict adapter as used in the Tag
 * suite would not see the temporary table. Strict mode is instead toggled
 * on the live session via SET SESSION sql_mode and restored afterwards.
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!$adapter instanceof Mysql) {
        $this->markTestSkipped(
            'CatalogRule index refresh query uses MySQL user variables (@price/@group_id); MySQL/MariaDB only.',
        );
    }
});

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Build a Refresh indexer instance wired to the live core_write connection.
 */
function catalogRuleBuildRefreshIndexer(): Mage_CatalogRule_Model_Action_Index_Refresh
{
    return Mage::getModel('catalogrule/action_index_refresh', [
        'connection' => Mage::getSingleton('core/resource')->getConnection('core_write'),
        'factory'    => Mage::getModel('core/factory'),
        'resource'   => Mage::getResourceModel('catalogrule/rule'),
        'app'        => Mage::app(),
    ]);
}

/**
 * Invoke a protected method on the Refresh indexer.
 */
function catalogRuleInvoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new \ReflectionMethod($object, $method);
    return $reflection->invokeArgs($object, $args);
}

/**
 * Seed the per-connection temporary table with two rule rows for the SAME
 * product / customer-group pair (same grouped_id). Two rows in one group is
 * exactly the shape that made the pre-fix bare latest_start_date /
 * earliest_end_date columns raise error 1055 under ONLY_FULL_GROUP_BY.
 *
 * Both rows use the 'to_fixed' operator so the expected MIN(rule_price) is
 * min(action_amount, price) regardless of how the user-variable chaining is
 * evaluated: 50 for the first row, 30 for the second — MIN is always 30.
 */
function catalogRuleSeedTemporaryTable(string $tmpTable, int $productId): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

    $common = [
        'grouped_id'        => $productId . '-0',
        'product_id'        => $productId,
        'customer_group_id' => 0,
        'action_operator'   => 'to_fixed',
        'action_stop'       => 0,
        'price'             => 100.0000,
        'from_time'         => 0,
        'to_time'           => 0,
    ];

    $adapter->insert($tmpTable, $common + [
        'from_date'       => '2024-01-01',
        'to_date'         => '2026-12-31',
        'action_amount'   => 50.0000,
        'sort_order'      => 1,
        'rule_product_id' => 1,
    ]);
    $adapter->insert($tmpTable, $common + [
        'from_date'       => '2024-02-01',
        'to_date'         => '2026-06-30',
        'action_amount'   => 30.0000,
        'sort_order'      => 2,
        'rule_product_id' => 2,
    ]);
}

/**
 * Run a closure with ONLY_FULL_GROUP_BY active on the live session
 * (genuine MySQL only; MariaDB is exempt, mirroring _initConnection),
 * restoring the previous sql_mode afterwards.
 */
function catalogRuleWithStrictGroupBy(\Closure $callback): mixed
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $isMariaDb = stripos((string) $adapter->fetchOne('SELECT VERSION()'), 'mariadb') !== false;

    $originalMode = null;
    if (!$isMariaDb) {
        $originalMode = (string) $adapter->fetchOne('SELECT @@SESSION.sql_mode');
        $adapter->query("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'");
    }

    try {
        return $callback();
    } finally {
        if ($originalMode !== null) {
            $adapter->query('SET SESSION sql_mode = ' . $adapter->quote($originalMode));
        }
    }
}

// ---------------------------------------------------------------------------
// Requirement 1: _prepareIndexSelect executes under strict GROUP BY and
// aggregates the per-group dates deterministically
// ---------------------------------------------------------------------------

it('executes _prepareIndexSelect under ONLY_FULL_GROUP_BY and aggregates latest_start_date / earliest_end_date deterministically', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $refresh = catalogRuleBuildRefreshIndexer();
    $website = Mage::app()->getWebsite();

    catalogRuleInvoke($refresh, '_createTemporaryTable');
    $tmpTable = catalogRuleInvoke($refresh, '_getTemporaryTable');

    try {
        // Fake product id is safe here: nothing is written to the real index table.
        catalogRuleSeedTemporaryTable($tmpTable, 990001);

        $rows = catalogRuleWithStrictGroupBy(function () use ($refresh, $website, $adapter) {
            /** @var \Maho\Db\Select $select */
            $select = catalogRuleInvoke($refresh, '_prepareIndexSelect', [$website, time()]);

            // Static regression: the two derived columns must be aggregate-wrapped.
            $sql = $select->assemble();
            expect($sql)->toContain('MAX(latest_start_date)');
            expect($sql)->toContain('MIN(earliest_end_date)');

            // Direct execution: a bare-column regression raises error 1055 here
            // and fails the test automatically.
            return $adapter->fetchAll($select);
        });

        // One group x three rule dates (yesterday / today / tomorrow).
        expect($rows)->toHaveCount(3);

        foreach ($rows as $row) {
            expect((int) $row['product_id'])->toBe(990001);
            expect((int) $row['customer_group_id'])->toBe(0);
            expect((int) $row['website_id'])->toBe((int) $website->getId());
            // MAX of ('2024-01-01', '2024-02-01') — the alias says "latest".
            expect($row['latest_start_date'])->toBe('2024-02-01');
            // MIN of ('2026-12-31', '2026-06-30') — the alias says "earliest".
            expect($row['earliest_end_date'])->toBe('2026-06-30');
            // to_fixed rows: min(50, 100) = 50 and min(30, 50 or 100) = 30 → MIN = 30.
            expect((float) $row['rule_price'])->toBe(30.0);
        }
    } finally {
        $adapter->dropTemporaryTable($tmpTable);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: _fillIndexData (insertFromSelect over _prepareIndexSelect)
// writes aggregated rows into catalogrule_product_price under strict mode
// ---------------------------------------------------------------------------

it('fills the rule product price index via _fillIndexData without strict GROUP BY errors and with aggregated dates', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    // The index table has a FK to catalog_product_entity, so a real product is required.
    $productId = (int) $adapter->fetchOne(
        $adapter->select()->from($resource->getTableName('catalog/product'), ['entity_id'])->limit(1),
    );
    if (!$productId) {
        $this->markTestSkipped('No products available to satisfy the price index foreign key.');
    }

    $refresh = catalogRuleBuildRefreshIndexer();
    $website = Mage::app()->getWebsite();
    $priceTable = $resource->getTableName('catalogrule/rule_product_price');
    $cleanup = [
        'product_id = ?' => $productId,
        'website_id = ?' => (int) $website->getId(),
        'customer_group_id = ?' => 0,
    ];

    catalogRuleInvoke($refresh, '_createTemporaryTable');
    $tmpTable = catalogRuleInvoke($refresh, '_getTemporaryTable');

    try {
        catalogRuleSeedTemporaryTable($tmpTable, $productId);
        $adapter->delete($priceTable, $cleanup);

        // insertFromSelect(_prepareIndexSelect): throws under ONLY_FULL_GROUP_BY
        // on regression, and also verifies the column order/count still matches
        // the target table.
        catalogRuleWithStrictGroupBy(function () use ($refresh, $website) {
            catalogRuleInvoke($refresh, '_fillIndexData', [$website, time()]);
        });

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($priceTable)
                ->where('product_id = ?', $productId)
                ->where('website_id = ?', (int) $website->getId())
                ->where('customer_group_id = ?', 0),
        );

        // One group x three rule dates, deduplicated by the unique index.
        expect($rows)->toHaveCount(3);
        $ruleDates = [];
        foreach ($rows as $row) {
            $ruleDates[] = $row['rule_date'];
            expect($row['latest_start_date'])->toBe('2024-02-01');
            expect($row['earliest_end_date'])->toBe('2026-06-30');
            expect((float) $row['rule_price'])->toBe(30.0);
        }
        expect(array_unique($ruleDates))->toHaveCount(3);
    } finally {
        $adapter->delete($priceTable, $cleanup);
        $adapter->dropTemporaryTable($tmpTable);
    }
});
