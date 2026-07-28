<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the aggregation Mage_CatalogRule_Model_Action_Index_Refresh::_fillIndexData()
 * performs while walking the temporary table: rule rows sharing a product / customer group collapse
 * into one index row per rule date, carrying the cheapest rule_price, the latest from_date and the
 * earliest to_date among the rules applicable on that date.
 *
 * This used to be a GROUP BY over a select chained with MySQL session variables (@price, @group_id,
 * @action_stop). That raised error 1055 under ONLY_FULL_GROUP_BY whenever the two dates were not
 * aggregate-wrapped (#1111) and could not execute on PostgreSQL or SQLite at all, so the coverage
 * skipped those adapters. The running calculation now happens in PHP and these expectations are
 * checked on every backend.
 *
 * Because temporary tables are per-connection, seeding and the refresh itself must run on the same
 * live core_write connection.
 */

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
 * Seed the per-connection temporary table with two rule rows for the SAME product / customer-group
 * pair (same grouped_id). Two rows in one group is exactly the shape the aggregation has to collapse.
 *
 * Both rows use the 'to_fixed' operator so the expected cheapest price is min(action_amount, price)
 * however the chain evaluates: 50 for the first row, 30 for the second, so the minimum is 30.
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

it('collapses the rule rows of one group into one index row per rule date', function () {
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

        catalogRuleInvoke($refresh, '_fillIndexData', [$website, time()]);

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($priceTable)
                ->where('product_id = ?', $productId)
                ->where('website_id = ?', (int) $website->getId())
                ->where('customer_group_id = ?', 0),
        );

        // One group x three rule dates (yesterday / today / tomorrow).
        expect($rows)->toHaveCount(3);
        $ruleDates = [];
        foreach ($rows as $row) {
            $ruleDates[] = $row['rule_date'];
            // Latest of ('2024-01-01', '2024-02-01'), as the alias says.
            expect($row['latest_start_date'])->toBe('2024-02-01');
            // Earliest of ('2026-12-31', '2026-06-30'), as the alias says.
            expect($row['earliest_end_date'])->toBe('2026-06-30');
            // to_fixed rows: min(50, 100) = 50, then min(30, 50) = 30, so the cheapest is 30.
            expect((float) $row['rule_price'])->toBe(30.0);
        }
        expect(array_unique($ruleDates))->toHaveCount(3);
    } finally {
        $adapter->delete($priceTable, $cleanup);
        $adapter->dropTemporaryTable($tmpTable);
    }
});
