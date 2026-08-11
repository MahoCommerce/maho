<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fix in
 * Maho_AdminActivityLog_Block_Adminhtml_Activity_Grid::_prepareCollection().
 *
 * The old query grouped the outer SELECT main_table.* by
 * COALESCE(action_group_id, activity_id), which violates ONLY_FULL_GROUP_BY on
 * MySQL and is rejected by PostgreSQL (cross-type COALESCE + ungrouped columns).
 * The fix joins a derived aggregate that picks MAX(activity_id) per group as the
 * deterministic representative row and exposes COUNT(*) AS activity_count, so
 * the outer select stays ungrouped.
 *
 * Strict-mode note (same approach as the Tag suite): MahoBackendTestCase opens
 * the DB connection before tests run, so on MySQL the assembled SQL is executed
 * through a freshly created adapter opened after Mage::setIsDeveloperMode(true),
 * which applies ONLY_FULL_GROUP_BY. PostgreSQL enforces strict GROUP BY natively.
 * SQLite has no strict mode, so the strict-execution test is skipped there —
 * the behavior tests still run on every engine.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Adapter with strict GROUP BY guaranteed active (fresh connection on MySQL).
 */
function adminActivityLogStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
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
 * Build the exact collection the activity grid uses, by invoking the real
 * (fixed) Maho_AdminActivityLog_Block_Adminhtml_Activity_Grid::_prepareCollection().
 * _isExport is toggled so the parent grid prepares the query without loading it,
 * letting tests add fixture filters before execution.
 */
function adminActivityLogGridCollection(): Maho_AdminActivityLog_Model_Resource_Activity_Collection
{
    $block = Mage::app()->getLayout()->createBlock('adminactivitylog/adminhtml_activity_grid');

    $isExport = new ReflectionProperty(Mage_Adminhtml_Block_Widget_Grid::class, '_isExport');
    $isExport->setValue($block, true);

    (new ReflectionMethod($block, '_prepareCollection'))->invoke($block);

    return $block->getCollection();
}

/**
 * Insert one action group (3 activities sharing an action_group_id) plus two
 * standalone activities (action_group_id NULL). Returns the fixture layout.
 */
function adminActivityLogInsertFixture(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('adminactivitylog/activity');

    $username = 'maho-test-' . uniqid();
    $groupId = 'maho-test-group-' . uniqid();

    $rows = [
        ['action_group_id' => $groupId, 'created_at' => '2026-01-01 10:00:00'],
        ['action_group_id' => $groupId, 'created_at' => '2026-01-01 10:05:00'],
        ['action_group_id' => $groupId, 'created_at' => '2026-01-01 10:10:00'],
        ['action_group_id' => null,     'created_at' => '2026-01-02 09:00:00'],
        ['action_group_id' => null,     'created_at' => '2026-01-03 09:00:00'],
    ];

    $groupedIds = [];
    $standaloneIds = [];
    foreach ($rows as $row) {
        $adapter->insert($table, [
            'action_group_id' => $row['action_group_id'],
            'username'        => $username,
            'action_type'     => 'save',
            'entity_type'     => 'maho/test',
            'entity_id'       => 1,
            'ip_address'      => '127.0.0.1',
            'request_url'     => '/admin/maho-test',
            'created_at'      => $row['created_at'],
        ]);
        $id = (int) $adapter->lastInsertId($table);
        if ($row['action_group_id'] === null) {
            $standaloneIds[] = $id;
        } else {
            $groupedIds[] = $id;
        }
    }

    return [
        'username'       => $username,
        'group_id'       => $groupId,
        'grouped_ids'    => $groupedIds,
        'standalone_ids' => $standaloneIds,
    ];
}

function adminActivityLogCleanFixture(array $fixture): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete(
        $resource->getTableName('adminactivitylog/activity'),
        ['username = ?' => $fixture['username']],
    );
}

// ---------------------------------------------------------------------------
// Requirement 1: the grid query executes under strict GROUP BY
// ---------------------------------------------------------------------------

it('executes the activity grid select and its pager count without strict GROUP BY errors', function () {
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($liveAdapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no strict GROUP BY mode; test is MySQL/PostgreSQL only.');
    }

    $fixture = adminActivityLogInsertFixture();

    try {
        $collection = adminActivityLogGridCollection();
        $strictAdapter = adminActivityLogStrictAdapter();

        // Pager count query used by getSize(). Fetching it first also renders the
        // collection filters, which applies the deferred action-group dedup join.
        $count = (int) $strictAdapter->fetchOne($collection->getSelectCountSql()->assemble());
        expect($count)->toBeGreaterThanOrEqual(3);

        // Main grid select: SELECT main_table.*, grp.activity_count with the
        // derived-aggregate join. Throws on a GROUP BY violation (1055 / 42803),
        // which fails the test automatically.
        $rows = $strictAdapter->fetchAll($collection->getSelect()->assemble());
        expect($rows)->not->toBeEmpty();
        expect($rows[0])->toHaveKeys(['activity_id', 'username', 'created_at', 'activity_count']);
    } finally {
        adminActivityLogCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: dedup, representative row, and per-group counts
// ---------------------------------------------------------------------------

it('collapses each action group to its latest activity with the correct activity_count', function () {
    $fixture = adminActivityLogInsertFixture();

    try {
        $collection = adminActivityLogGridCollection();
        $collection->addFieldToFilter('main_table.username', $fixture['username']);
        $collection->setOrder('main_table.created_at', 'DESC');
        $collection->load();

        // 3 grouped rows collapse to 1 representative; 2 standalone rows stay.
        expect(count($collection->getItems()))->toBe(3);

        $groupedItem = null;
        foreach ($collection as $item) {
            if ($item->getActionGroupId() === $fixture['group_id']) {
                $groupedItem = $item;
            } else {
                // Standalone activities are their own group of one.
                expect((int) $item->getActivityCount())->toBe(1);
                expect((int) $item->getActivityId())->toBeIn($fixture['standalone_ids']);
            }
        }

        // The representative row is deterministically the latest (MAX activity_id).
        expect($groupedItem)->not->toBeNull();
        expect((int) $groupedItem->getActivityId())->toBe(max($fixture['grouped_ids']));
        expect((int) $groupedItem->getActivityCount())->toBe(3);
        expect($groupedItem->getCreatedAt())->toStartWith('2026-01-01 10:10:00');

        // ORDER BY created_at DESC on the ungrouped outer select stays valid and
        // correct: the newest standalone activity comes first.
        $first = null;
        foreach ($collection as $item) {
            $first = $item;
            break;
        }
        expect((int) $first->getActivityId())->toBe(max($fixture['standalone_ids']));
    } finally {
        adminActivityLogCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: getSize() counts groups, not underlying activities
// ---------------------------------------------------------------------------

it('reports one pager row per action group via getSize()', function () {
    $fixture = adminActivityLogInsertFixture();

    try {
        $collection = adminActivityLogGridCollection();
        $collection->addFieldToFilter('main_table.username', $fixture['username']);

        // 5 activities → 3 groups (1 grouped + 2 standalone).
        expect($collection->getSize())->toBe(3);
    } finally {
        adminActivityLogCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: grid filters interact correctly with the dedup — filtering by
// an activity_id that is NOT its group's representative must still return that
// row (the dedup is computed within the filtered set, not over the whole table)
// ---------------------------------------------------------------------------

it('returns a non-representative activity when the grid is filtered by its id', function () {
    $fixture = adminActivityLogInsertFixture();

    try {
        // The oldest activity of the group: never the MAX(activity_id) representative.
        $nonRepresentativeId = min($fixture['grouped_ids']);
        expect($nonRepresentativeId)->not->toBe(max($fixture['grouped_ids']));

        $collection = adminActivityLogGridCollection();
        $collection->addFieldToFilter('main_table.activity_id', $nonRepresentativeId);
        $collection->load();

        $items = $collection->getItems();
        expect(count($items))->toBe(1);

        $item = array_values($items)[0];
        expect((int) $item->getActivityId())->toBe($nonRepresentativeId);
        // Within the filtered set the group contains a single activity.
        expect((int) $item->getActivityCount())->toBe(1);
    } finally {
        adminActivityLogCleanFixture($fixture);
    }
});
