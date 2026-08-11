<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fixes in
 * Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Timebased.
 *
 * The bug: five attribute cases (days_since_last_login, days_since_last_order,
 * days_inactive, days_since_first_order, order_frequency_days) referenced the
 * SELECT-list alias "days" in HAVING. PostgreSQL never resolves output aliases
 * in HAVING, and referencing a non-aggregated alias is also fragile under MySQL
 * ONLY_FULL_GROUP_BY. The fix inlines the aggregate DATEDIFF expression (and,
 * for order_frequency_days, the whole division expression) via buildSqlCondition().
 *
 * Strict-mode enforcement: MahoBackendTestCase::setUp() opens the connection
 * before beforeEach() runs, so on MySQL the generated SQL is executed through a
 * freshly created adapter opened after Mage::setIsDeveloperMode(true), which
 * applies ONLY_FULL_GROUP_BY in _initConnection(). PostgreSQL enforces strict
 * GROUP BY natively, so the live adapter is sufficient there. SQLite has no
 * strict GROUP BY mode and is skipped (MariaDB's ONLY_FULL_GROUP_BY is exempted
 * by the adapter itself, so the queries simply run unrestricted there).
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode; test is MySQL/PostgreSQL only.');
    }
});

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Adapter with strict GROUP BY active: a fresh connection on MySQL (so that
 * developer mode is honored by _initConnection), the live singleton elsewhere.
 */
function segTimebasedStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($liveAdapter instanceof Mysql) {
        Mage::setIsDeveloperMode(true);
        return new Mysql($liveAdapter->getConfig());
    }

    return $liveAdapter;
}

/**
 * Build the subfilter SQL for a time-based condition (attribute, operator, value).
 */
function segTimebasedConditionSql(string $attribute, string $operator, string $value): string
{
    /** @var Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Timebased $condition */
    $condition = Mage::getModel('customersegmentation/segment_condition_customer_timebased');
    $condition->setAttribute($attribute);
    $condition->setOperator($operator);
    $condition->setValue($value);

    return $condition->getSubfilterSql('e.entity_id', true, null);
}

/**
 * Execute a time-based condition against the customer entity table through the
 * strict-mode adapter and return the matched customer ids. Throws on any strict
 * GROUP BY violation (error 1055 on MySQL, 42803 on PostgreSQL), which makes
 * the calling test fail automatically.
 *
 * @return int[]
 */
function segTimebasedMatchedIds(string $attribute, string $operator, string $value): array
{
    $customerTable = Mage::getSingleton('core/resource')->getTableName('customer/entity');
    $sql = "SELECT e.entity_id FROM {$customerTable} AS e WHERE "
        . segTimebasedConditionSql($attribute, $operator, $value);

    return array_map('intval', segTimebasedStrictAdapter()->fetchCol($sql));
}

/**
 * Create three customers exercising every fixed query path:
 *  - "recent":  login 5 days ago, two orders (10 and 40 days ago) -> avg frequency 30 days
 *  - "single":  no login, one order 100 days ago
 *  - "dormant": no login, no orders, registered 200 days ago
 *
 * @return array{customers: array<string, int>, orders: int[]}
 */
function segTimebasedCreateFixture(): array
{
    $uniqueId = uniqid('strict_groupby_', true);
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $logTable = $resource->getTableName('log/customer');

    $definitions = [
        'recent' => [
            'created_days_ago' => 60,
            'login_days_ago' => 5,
            'order_days_ago' => [10, 40],
        ],
        'single' => [
            'created_days_ago' => 150,
            'login_days_ago' => null,
            'order_days_ago' => [100],
        ],
        'dormant' => [
            'created_days_ago' => 200,
            'login_days_ago' => null,
            'order_days_ago' => [],
        ],
    ];

    $fixture = ['customers' => [], 'orders' => []];

    foreach ($definitions as $key => $definition) {
        $customer = Mage::getModel('customer/customer');
        $customer->setFirstname('Strict');
        $customer->setLastname('GroupBy ' . ucfirst($key));
        $customer->setEmail("{$key}.{$uniqueId}@strictgroupby.test");
        $customer->setGroupId(1);
        $customer->setWebsiteId(1);
        $customer->setCreatedAt(date('Y-m-d H:i:s', strtotime("-{$definition['created_days_ago']} days")));
        $customer->save();
        $fixture['customers'][$key] = (int) $customer->getId();

        if ($definition['login_days_ago'] !== null) {
            $adapter->insert($logTable, [
                'customer_id' => $customer->getId(),
                'login_at' => date('Y-m-d H:i:s', strtotime("-{$definition['login_days_ago']} days")),
                'logout_at' => null,
                'store_id' => 1,
            ]);
        }

        foreach ($definition['order_days_ago'] as $daysAgo) {
            $order = Mage::getModel('sales/order');
            $order->setCustomerId($customer->getId());
            $order->setCustomerEmail($customer->getEmail());
            $order->setGrandTotal(100.00);
            $order->setState(Mage_Sales_Model_Order::STATE_NEW);
            $order->setStatus('pending');
            $order->setStoreId(1);
            $order->setCreatedAt(date('Y-m-d H:i:s', strtotime("-{$daysAgo} days")));
            $order->save();
            $fixture['orders'][] = (int) $order->getId();
        }
    }

    return $fixture;
}

/**
 * Remove all fixture rows (orders, login log entries, customers).
 *
 * @param array{customers: array<string, int>, orders: int[]} $fixture
 */
function segTimebasedCleanFixture(array $fixture): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    foreach ($fixture['orders'] as $orderId) {
        Mage::getModel('sales/order')->load($orderId)->delete();
    }

    if ($fixture['customers'] !== []) {
        $adapter->delete(
            $resource->getTableName('log/customer'),
            ['customer_id IN (?)' => array_values($fixture['customers'])],
        );
    }

    foreach ($fixture['customers'] as $customerId) {
        Mage::getModel('customer/customer')->load($customerId)->delete();
    }
}

// ---------------------------------------------------------------------------
// Structural regression: HAVING must inline the aggregate, never the alias
// ---------------------------------------------------------------------------

it('never references the SELECT-list alias "days" in HAVING for any fixed attribute', function () {
    $attributes = [
        'days_since_last_login',
        'days_since_last_order',
        'days_inactive',
        'days_since_first_order',
        'order_frequency_days',
    ];

    foreach ($attributes as $attribute) {
        $sql = segTimebasedConditionSql($attribute, '>=', '30');

        // Every fixed path filters grouped rows, so a HAVING clause must be present.
        expect($sql)->toContain('HAVING');

        // The pre-fix shape was "HAVING days >= 30" (or "HAVING (days) >= 30"):
        // a bare output alias that PostgreSQL never resolves. The fix inlines the
        // aggregate expression, so no comparison may target the bare alias.
        expect(preg_match('/HAVING\s+\(*\s*days\s*[<>=!]/i', $sql))
            ->toBe(0, "Alias 'days' referenced in HAVING for attribute {$attribute}: {$sql}");

        // The alias must still exist in the SELECT list (the outer timedata
        // wrapper and returned columns are unchanged).
        expect(preg_match('/AS\s+["`]?days["`]?/i', $sql))->toBe(1);
    }

    // order_frequency_days keeps its already-compliant minimum-orders guard.
    expect(segTimebasedConditionSql('order_frequency_days', '>=', '30'))->toContain('COUNT(*) > 1');
});

// ---------------------------------------------------------------------------
// days_since_last_login
// ---------------------------------------------------------------------------

it('executes days_since_last_login under strict GROUP BY and matches by last login recency', function () {
    $fixture = segTimebasedCreateFixture();

    try {
        // Executed through the strict adapter: throws on GROUP BY violation.
        $matched = segTimebasedMatchedIds('days_since_last_login', '<=', '30');

        // "recent" logged in 5 days ago; the others have no login rows at all.
        expect($matched)->toContain($fixture['customers']['recent']);
        expect($matched)->not->toContain($fixture['customers']['single']);
        expect($matched)->not->toContain($fixture['customers']['dormant']);

        // Inverse operator: 5 days ago is not "> 30 days since last login".
        $matchedOld = segTimebasedMatchedIds('days_since_last_login', '>', '30');
        expect($matchedOld)->not->toContain($fixture['customers']['recent']);
    } finally {
        segTimebasedCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// days_since_last_order and days_since_first_order
// ---------------------------------------------------------------------------

it('executes days_since_last_order and days_since_first_order under strict GROUP BY with correct MAX/MIN semantics', function () {
    $fixture = segTimebasedCreateFixture();

    try {
        // Last order: "recent" ordered 10 days ago, "single" 100 days ago.
        $matchedRecentOrders = segTimebasedMatchedIds('days_since_last_order', '<=', '30');
        expect($matchedRecentOrders)->toContain($fixture['customers']['recent']);
        expect($matchedRecentOrders)->not->toContain($fixture['customers']['single']);
        expect($matchedRecentOrders)->not->toContain($fixture['customers']['dormant']);

        $matchedOldOrders = segTimebasedMatchedIds('days_since_last_order', '>', '60');
        expect($matchedOldOrders)->toContain($fixture['customers']['single']);
        expect($matchedOldOrders)->not->toContain($fixture['customers']['recent']);

        // First order: "recent" first ordered 40 days ago, "single" 100 days ago.
        $matchedOldFirst = segTimebasedMatchedIds('days_since_first_order', '>', '60');
        expect($matchedOldFirst)->toContain($fixture['customers']['single']);
        expect($matchedOldFirst)->not->toContain($fixture['customers']['recent']);

        // Customers without orders never match days_since_first_order.
        $matchedAnyFirst = segTimebasedMatchedIds('days_since_first_order', '>=', '0');
        expect($matchedAnyFirst)->not->toContain($fixture['customers']['dormant']);
    } finally {
        segTimebasedCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// days_inactive
// ---------------------------------------------------------------------------

it('executes days_inactive under strict GROUP BY using the latest of login, order, and registration', function () {
    $fixture = segTimebasedCreateFixture();

    try {
        $matched = segTimebasedMatchedIds('days_inactive', '>', '90');

        // "single" last ordered 100 days ago; "dormant" has no activity at all,
        // so registration 200 days ago is the fallback. "recent" logged in 5
        // days ago, so it must not match.
        expect($matched)->toContain($fixture['customers']['single']);
        expect($matched)->toContain($fixture['customers']['dormant']);
        expect($matched)->not->toContain($fixture['customers']['recent']);
    } finally {
        segTimebasedCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// order_frequency_days
// ---------------------------------------------------------------------------

it('executes order_frequency_days under strict GROUP BY and requires at least two orders', function () {
    $fixture = segTimebasedCreateFixture();

    try {
        // "recent" has orders 10 and 40 days ago: span 30 days / 1 interval = 30.
        $matched = segTimebasedMatchedIds('order_frequency_days', '<=', '60');
        expect($matched)->toContain($fixture['customers']['recent']);
        expect($matched)->not->toContain($fixture['customers']['single']);
        expect($matched)->not->toContain($fixture['customers']['dormant']);

        // The average is ~30 days, so a tight upper bound must exclude it.
        $matchedTight = segTimebasedMatchedIds('order_frequency_days', '<=', '10');
        expect($matchedTight)->not->toContain($fixture['customers']['recent']);

        // The COUNT(*) > 1 guard: single-order customers never match, even for >= 0.
        $matchedAny = segTimebasedMatchedIds('order_frequency_days', '>=', '0');
        expect($matchedAny)->not->toContain($fixture['customers']['single']);
    } finally {
        segTimebasedCleanFixture($fixture);
    }
});
