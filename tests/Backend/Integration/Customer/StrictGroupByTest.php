<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression tests for the strict-GROUP-BY rewrite of
 * Mage_Customer_Model_Resource_Customer_Collection::groupByEmail().
 *
 * The fix moved the GROUP BY into a grouped derived table (email,
 * MIN(entity_id) AS entity_id, COUNT(*) AS email_count) joined inner on
 * e.entity_id, keeping the main select ungrouped so e.*, EAV attribute
 * loading, and getSelectCountSql()/getSize() stay legal under
 * ONLY_FULL_GROUP_BY (MySQL) and native strict grouping (PostgreSQL).
 *
 * Strict-mode enforcement note: MahoBackendTestCase::setUp() opens the DB
 * connection before beforeEach() runs, so on MySQL the assembled SQL is
 * re-executed through a freshly opened adapter created after
 * Mage::setIsDeveloperMode(true) (which enables ONLY_FULL_GROUP_BY).
 * PostgreSQL always enforces strict GROUP BY natively. The behavioral
 * tests (dedup, deterministic representative, count) run on every engine
 * including SQLite.
 */

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Create a fresh MySQL adapter with ONLY_FULL_GROUP_BY active.
 * On PostgreSQL the live singleton is sufficient (strict mode is always on).
 */
function customerStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($liveAdapter instanceof Mysql) {
        Mage::setIsDeveloperMode(true);
        return new Mysql($liveAdapter->getConfig());
    }

    // PostgreSQL always enforces strict GROUP BY.
    return $liveAdapter;
}

/**
 * Insert three customer rows directly into customer_entity:
 * two sharing one email and one with a distinct email. website_id is NULL,
 * which every engine treats as distinct in the (email, website_id) unique
 * index, so the duplicate email never violates the constraint.
 *
 * Returns [duplicate_email, unique_email, [entity_id, entity_id, entity_id]].
 */
function customerGroupByEmailFixture(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('customer/entity');
    $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType('customer')->getEntityTypeId();

    $token = uniqid();
    $duplicateEmail = "maho-test-dup-{$token}@example.com";
    $uniqueEmail = "maho-test-uniq-{$token}@example.com";

    $ids = [];
    foreach ([$duplicateEmail, $duplicateEmail, $uniqueEmail] as $email) {
        $adapter->insert($table, [
            'entity_type_id' => $entityTypeId,
            'attribute_set_id' => 0,
            'website_id' => null,
            'email' => $email,
            'group_id' => 1,
            'store_id' => 0,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
            'is_active' => 1,
        ]);
        $ids[] = (int) $adapter->lastInsertId($table);
    }

    return [$duplicateEmail, $uniqueEmail, $ids];
}

/**
 * Remove the fixture customer rows.
 */
function customerGroupByEmailCleanFixture(array $entityIds): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete(
        $resource->getTableName('customer/entity'),
        ['entity_id IN (?)' => $entityIds],
    );
}

/**
 * Build a customer collection with groupByEmail() applied, filtered down to
 * the fixture emails so pre-existing customers cannot affect assertions.
 */
function customerGroupByEmailCollection(array $emails): Mage_Customer_Model_Resource_Customer_Collection
{
    /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
    $collection = Mage::getResourceModel('customer/customer_collection');
    $collection->groupByEmail();
    $collection->addAttributeToFilter('email', ['in' => $emails]);
    return $collection;
}

// ---------------------------------------------------------------------------
// Requirement 1: groupByEmail() main select and count select are legal
// under strict GROUP BY
// ---------------------------------------------------------------------------

it('executes groupByEmail main and count selects without strict GROUP BY errors', function () {
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($liveAdapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode; test is MySQL/PostgreSQL only.');
    }

    [$duplicateEmail, $uniqueEmail, $ids] = customerGroupByEmailFixture();

    try {
        $collection = customerGroupByEmailCollection([$duplicateEmail, $uniqueEmail]);

        // Execute through a strict-mode adapter: any ONLY_FULL_GROUP_BY /
        // 42803 violation throws and fails the test automatically.
        $strictAdapter = customerStrictAdapter();
        $rows = $strictAdapter->fetchAll($collection->getSelect()->assemble());
        expect($rows)->toHaveCount(2);

        // The count path must also be legal: the main select is ungrouped,
        // so getSelectCountSql() needs no grouped-select wrapping.
        $count = (int) $strictAdapter->fetchOne($collection->getSelectCountSql()->assemble());
        expect($count)->toBe(2);
    } finally {
        customerGroupByEmailCleanFixture($ids);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: one row per distinct email, deterministic MIN(entity_id)
// representative, correct email_count
// ---------------------------------------------------------------------------

it('returns one deterministic representative row per email with the correct email_count', function () {
    [$duplicateEmail, $uniqueEmail, $ids] = customerGroupByEmailFixture();

    try {
        $collection = customerGroupByEmailCollection([$duplicateEmail, $uniqueEmail]);
        $collection->load();

        $itemsByEmail = [];
        foreach ($collection as $item) {
            $itemsByEmail[$item->getEmail()] = $item;
        }

        // One row per distinct email
        expect($itemsByEmail)->toHaveCount(2)
            ->and($itemsByEmail)->toHaveKey($duplicateEmail)
            ->and($itemsByEmail)->toHaveKey($uniqueEmail);

        // Deterministic representative: the MIN(entity_id) row survives,
        // not an engine-arbitrary one
        expect((int) $itemsByEmail[$duplicateEmail]->getId())->toBe(min($ids[0], $ids[1]));
        expect((int) $itemsByEmail[$uniqueEmail]->getId())->toBe($ids[2]);

        // email_count reflects how many customer rows share the email
        expect((int) $itemsByEmail[$duplicateEmail]->getEmailCount())->toBe(2);
        expect((int) $itemsByEmail[$uniqueEmail]->getEmailCount())->toBe(1);
    } finally {
        customerGroupByEmailCleanFixture($ids);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: getSize() counts distinct emails and matches the loaded
// item count
// ---------------------------------------------------------------------------

it('counts distinct emails via getSize() consistently with the loaded rows', function () {
    [$duplicateEmail, $uniqueEmail, $ids] = customerGroupByEmailFixture();

    try {
        // getSize() runs on an unloaded collection (fresh instance)
        $sizeCollection = customerGroupByEmailCollection([$duplicateEmail, $uniqueEmail]);
        expect($sizeCollection->getSize())->toBe(2);

        // Loaded item count must agree: 3 customer rows, 2 distinct emails
        $loadCollection = customerGroupByEmailCollection([$duplicateEmail, $uniqueEmail]);
        $loadCollection->load();
        expect(count($loadCollection->getItems()))->toBe(2);
    } finally {
        customerGroupByEmailCleanFixture($ids);
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: groupByEmail() composes with EAV attribute selection and
// addNameToSelect() (main select stays ungrouped, so extra columns are legal)
// ---------------------------------------------------------------------------

it('composes groupByEmail with addNameToSelect and EAV attribute loading', function () {
    [$duplicateEmail, $uniqueEmail, $ids] = customerGroupByEmailFixture();

    try {
        $collection = customerGroupByEmailCollection([$duplicateEmail, $uniqueEmail]);
        $collection->addNameToSelect();
        $collection->addAttributeToSelect(['firstname', 'lastname']);
        $collection->load();

        expect(count($collection->getItems()))->toBe(2);
        foreach ($collection as $item) {
            expect((int) $item->getEmailCount())->toBeGreaterThanOrEqual(1);
        }
    } finally {
        customerGroupByEmailCleanFixture($ids);
    }
});
