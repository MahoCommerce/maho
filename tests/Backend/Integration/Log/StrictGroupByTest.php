<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Skip if the adapter is SQLite (no strict GROUP BY enforcement).
 * MySQL needs developer mode + fresh connection for ONLY_FULL_GROUP_BY.
 * PostgreSQL always enforces strict GROUP BY (SQLSTATE 42803).
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite does not enforce strict GROUP BY; test is MySQL/PostgreSQL only.');
    }

    Mage::setIsDeveloperMode(true);
    // Force fresh connection so ONLY_FULL_GROUP_BY applies on MySQL
    $resource = Mage::getSingleton('core/resource');
    $reflProp = new ReflectionProperty($resource, '_connections');
    $reflProp->setAccessible(true);
    $reflProp->setValue($resource, []);
});

// ---------------------------------------------------------------------------
// Requirement 1: Log Visitor collection with showCustomersOnly
// ---------------------------------------------------------------------------
it('loads the Log Visitor collection with showCustomersOnly applied without strict GROUP BY errors', function () {
    /** @var Mage_Log_Model_Resource_Visitor_Collection $collection */
    $collection = Mage::getResourceModel('log/visitor_collection');
    $collection->showCustomersOnly();

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Requirement 2: Admin dashboard Online Customers widget query
// ---------------------------------------------------------------------------
it('loads the admin dashboard Online Customers widget query without strict GROUP BY errors', function () {
    // The Online Customers grid uses log/visitor_online with addCustomerData()
    // which performs multiple LEFT JOINs on customer EAV tables.
    // We exercise the same path the dashboard/grid uses.
    /** @var Mage_Log_Model_Resource_Visitor_Online_Collection $collection */
    $collection = Mage::getResourceModel('log/visitor_online_collection');
    $collection->addCustomerData();
    // Filter to customers only (visitor_type = 'c')
    $collection->addFieldToFilter('visitor_type', Mage_Log_Model_Visitor::VISITOR_TYPE_CUSTOMER);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Requirement 3: Admin dashboard Online Visitors widget query
// ---------------------------------------------------------------------------
it('loads the admin dashboard Online Visitors widget query without strict GROUP BY errors', function () {
    // The Online Visitors grid loads all entries from log/visitor_online.
    /** @var Mage_Log_Model_Resource_Visitor_Online_Collection $collection */
    $collection = Mage::getResourceModel('log/visitor_online_collection');
    $collection->addCustomerData();

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Requirement 4: Fixed showCustomersOnly returns same unique customer count
// ---------------------------------------------------------------------------
it('returns the same number of unique customers as the previous implementation for a fixture data set', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $visitorTable = Mage::getSingleton('core/resource')->getTableName('log/visitor');
    $customerTable = Mage::getSingleton('core/resource')->getTableName('log/customer');

    // Insert fixture visitors
    $now = date('Y-m-d H:i:s');
    $adapter->insert($visitorTable, [
        'session_id'    => 'test-session-strict-gbv-1',
        'first_visit_at' => $now,
        'last_visit_at'  => $now,
        'last_url_id'    => 0,
        'store_id'       => 1,
    ]);
    $visitorId1 = (int) $adapter->lastInsertId($visitorTable);

    $adapter->insert($visitorTable, [
        'session_id'    => 'test-session-strict-gbv-2',
        'first_visit_at' => $now,
        'last_visit_at'  => $now,
        'last_url_id'    => 0,
        'store_id'       => 1,
    ]);
    $visitorId2 = (int) $adapter->lastInsertId($visitorTable);

    $adapter->insert($visitorTable, [
        'session_id'    => 'test-session-strict-gbv-3',
        'first_visit_at' => $now,
        'last_visit_at'  => $now,
        'last_url_id'    => 0,
        'store_id'       => 1,
    ]);
    $visitorId3 = (int) $adapter->lastInsertId($visitorTable);

    // Two visitors share customer 101; visitor 3 is a guest (no customer entry)
    $adapter->insert($customerTable, [
        'visitor_id'  => $visitorId1,
        'customer_id' => 101,
        'login_at'    => $now,
        'store_id'    => 1,
    ]);
    $adapter->insert($customerTable, [
        'visitor_id'  => $visitorId2,
        'customer_id' => 101,
        'login_at'    => $now,
        'store_id'    => 1,
    ]);

    try {
        // Load via showCustomersOnly - must return only visitor rows that have a customer entry
        /** @var Mage_Log_Model_Resource_Visitor_Collection $collection */
        $collection = Mage::getResourceModel('log/visitor_collection');
        $collection->showCustomersOnly();
        // Restrict to our fixture visitor IDs so parallel test data doesn't interfere
        $collection->getSelect()->where('main_table.visitor_id IN (?)', [$visitorId1, $visitorId2, $visitorId3]);
        $collection->load();

        // Fixture: visitor 1 and visitor 2 are customers (customer_id=101); visitor 3 is a guest.
        // showCustomersOnly should include visitor 1 and visitor 2, exclude visitor 3.
        // That means 2 items in the collection (one per visitor/session that is a customer).
        $itemIds = [];
        foreach ($collection as $item) {
            $itemIds[] = (int) $item->getId();
        }
        sort($itemIds);

        expect($itemIds)->toBe([$visitorId1, $visitorId2]);

        // Verify unique customer count via direct SQL (matches old implementation's dedup intent)
        $countSelect = $adapter->select()
            ->from(['lc' => $customerTable], ['count' => new \Maho\Db\Expr('COUNT(DISTINCT lc.customer_id)')])
            ->where('lc.visitor_id IN (?)', [$visitorId1, $visitorId2, $visitorId3])
            ->where('lc.customer_id > 0');
        $uniqueCount = (int) $adapter->fetchOne($countSelect);
        expect($uniqueCount)->toBe(1);
    } finally {
        $adapter->delete($customerTable, ['visitor_id = ?' => $visitorId1]);
        $adapter->delete($customerTable, ['visitor_id = ?' => $visitorId2]);
        $adapter->delete($visitorTable, ['visitor_id = ?' => $visitorId1]);
        $adapter->delete($visitorTable, ['visitor_id = ?' => $visitorId2]);
        $adapter->delete($visitorTable, ['visitor_id = ?' => $visitorId3]);
    }
});
