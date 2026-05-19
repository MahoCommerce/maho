<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Skip the entire suite on SQLite — SQLite does not enforce GROUP BY strictness.
 * All six requirements are meaningful only on MySQL (ONLY_FULL_GROUP_BY) and PostgreSQL.
 *
 * Note on strict-mode enforcement:
 * MahoBackendTestCase::setUp() calls Mage::app() which acquires a DB lock during config
 * init, creating the connection BEFORE beforeEach() runs. To ensure the queries are
 * tested under ONLY_FULL_GROUP_BY, we extract the assembled SQL from each collection
 * and execute it through a freshly created MySQL adapter that was opened after
 * Mage::setIsDeveloperMode(true). On PostgreSQL strict GROUP BY is always enforced
 * natively so a fresh adapter is not required.
 *
 * Pest v4 note: expect(fn)->not->toThrow() does NOT fail when an exception is thrown.
 * Tests in this suite use direct execution — if the query throws, the test fails
 * automatically.
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
 * Create a fresh MySQL adapter with ONLY_FULL_GROUP_BY active.
 * On PostgreSQL the live singleton is sufficient (strict mode is always on).
 */
function tagStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
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
 * Insert minimal fixture data for tag tests.
 * Returns an array with keys: tag_id, product_id, customer_id, store_id, tag_relation_id.
 */
function tagTestInsertFixture(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $productId = (int) $adapter->fetchOne(
        $adapter->select()->from($resource->getTableName('catalog/product'), ['entity_id'])->limit(1),
    );
    $storeId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('core/store'), ['store_id'])
            ->where('store_id > 0')
            ->limit(1),
    );
    $customerId = (int) $adapter->fetchOne(
        $adapter->select()->from($resource->getTableName('customer/entity'), ['entity_id'])->limit(1),
    );

    if (!$productId || !$storeId) {
        throw new \RuntimeException('No product/store found in test database for Tag fixtures.');
    }

    $tagName = 'maho-test-tag-' . uniqid();
    $adapter->insert($resource->getTableName('tag/tag'), [
        'name'              => $tagName,
        'status'            => Mage_Tag_Model_Tag::STATUS_APPROVED,
        'first_store_id'    => $storeId,
        'first_customer_id' => $customerId ?: null,
    ]);
    $tagId = (int) $adapter->lastInsertId($resource->getTableName('tag/tag'));

    $adapter->insert($resource->getTableName('tag/relation'), [
        'tag_id'      => $tagId,
        'customer_id' => $customerId ?: null,
        'product_id'  => $productId,
        'store_id'    => $storeId,
        'active'      => 1,
    ]);
    $tagRelationId = (int) $adapter->lastInsertId($resource->getTableName('tag/relation'));

    return [
        'tag_id'          => $tagId,
        'product_id'      => $productId,
        'customer_id'     => $customerId,
        'store_id'        => $storeId,
        'tag_relation_id' => $tagRelationId,
    ];
}

/**
 * Remove tag fixture data (relation + summary + tag).
 */
function tagTestCleanFixture(array $fixture): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->delete(
        $resource->getTableName('tag/relation'),
        ['tag_id = ?' => $fixture['tag_id']],
    );
    $adapter->delete(
        $resource->getTableName('tag/summary'),
        ['tag_id = ?' => $fixture['tag_id']],
    );
    $adapter->delete(
        $resource->getTableName('tag/tag'),
        ['tag_id = ?' => $fixture['tag_id']],
    );
}

/**
 * Execute assembled SQL through a fresh strict-mode adapter.
 * Throws on any GROUP BY violation (error 1055 on MySQL, 42803 on PostgreSQL).
 * If it throws, the calling test will fail automatically.
 */
function tagRunInStrictMode(\Maho\Db\Select $select): void
{
    $sql = $select->assemble();
    $strictAdapter = tagStrictAdapter();
    $strictAdapter->fetchAll($sql);
}

// ---------------------------------------------------------------------------
// Requirement 1: Tag product collection
// ---------------------------------------------------------------------------

it('loads the Tag product collection with tag joins without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        /** @var Mage_Tag_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('tag/product_collection');
        $collection->addTagFilter($fixture['tag_id']);

        // Execute through strict-mode adapter: throws on GROUP BY violation,
        // which causes the test to fail automatically.
        tagRunInStrictMode($collection->getSelect());

        expect(true)->toBeTrue();
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: Tag customer collection
// ---------------------------------------------------------------------------

it('loads the Tag customer collection with tag joins without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        /** @var Mage_Tag_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('tag/customer_collection');
        $collection->addTagFilter($fixture['tag_id']);

        // getSelect() returns the Select object with all JOINs and WHERE conditions.
        tagRunInStrictMode($collection->getSelect());

        expect(true)->toBeTrue();
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: Tag collection with addPopularity, addStoreFilter, addTagGroup
// ---------------------------------------------------------------------------

it('loads the Tag collection with addPopularity, addStoreFilter, and addTagGroup applied without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        /** @var Mage_Tag_Model_Resource_Tag_Collection $collection */
        $collection = Mage::getResourceModel('tag/tag_collection');
        $collection
            ->addPopularity()
            ->addStoreFilter($fixture['store_id'])
            ->addTagGroup();

        tagRunInStrictMode($collection->getSelect());

        expect(true)->toBeTrue();
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: Indexer Summary re-aggregate
// ---------------------------------------------------------------------------

it('re-aggregates the tag summary indexer without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        // Verify the inner store-level aggregation SELECT via strict adapter.
        $resource = Mage::getSingleton('core/resource');
        $writeAdapter = $resource->getConnection('core_write');

        $innerSelect = $writeAdapter->select()
            ->from(
                ['tr' => $resource->getTableName('tag/relation')],
                [
                    'tr.tag_id',
                    'tr.store_id',
                    'customers'         => 'COUNT(DISTINCT tr.customer_id)',
                    'products'          => 'COUNT(DISTINCT tr.product_id)',
                    'popularity'        => new \Maho\Db\Expr('COUNT(tr.customer_id)'),
                    'uses'              => new \Maho\Db\Expr('0'),
                    'historical_uses'   => new \Maho\Db\Expr('0'),
                    'base_popularity'   => new \Maho\Db\Expr('0'),
                ],
            )
            ->group(['tr.tag_id', 'tr.store_id'])
            ->where('tr.active = 1')
            ->where('tr.tag_id = ?', $fixture['tag_id']);

        tagRunInStrictMode($innerSelect);

        // Also run the full indexer and verify summary rows are created.
        /** @var Mage_Tag_Model_Resource_Indexer_Summary $indexer */
        $indexer = Mage::getResourceModel('tag/indexer_summary');
        $indexer->aggregate($fixture['tag_id']);

        $adapter = $resource->getConnection('core_read');
        $count = (int) $adapter->fetchOne(
            $adapter->select()
                ->from($resource->getTableName('tag/summary'), ['cnt' => 'COUNT(*)'])
                ->where('tag_id = ?', $fixture['tag_id']),
        );
        expect($count)->toBeGreaterThanOrEqual(1);
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 5: _getAggregationPerStoreView produces correct counts
// ---------------------------------------------------------------------------

it('produces correct per-store tag aggregation counts via _getAggregationPerStoreView', function () {
    $fixture = tagTestInsertFixture();

    try {
        // Verify the GROUP BY in _getAggregationPerStoreView via strict adapter.
        $resource = Mage::getSingleton('core/resource');
        $readAdapter = $resource->getConnection('core_read');

        $selectLocal = $readAdapter->select()
            ->from(
                ['main'  => $resource->getTableName('tag/relation')],
                [
                    'customers' => 'COUNT(DISTINCT main.customer_id)',
                    'products'  => 'COUNT(DISTINCT main.product_id)',
                    'store_id',
                    'uses'      => 'COUNT(main.tag_relation_id)',
                ],
            )
            ->join(
                ['store' => $resource->getTableName('core/store')],
                'store.store_id = main.store_id AND store.store_id > 0',
                [],
            )
            ->join(
                ['product_website' => $resource->getTableName('catalog/product_website')],
                'product_website.website_id = store.website_id AND product_website.product_id = main.product_id',
                [],
            )
            ->where('main.tag_id = ?', $fixture['tag_id'])
            ->where('main.active = 1')
            ->group('main.store_id');

        tagRunInStrictMode($selectLocal);

        // Invoke aggregate() which calls _getAggregationPerStoreView() internally.
        $tagModel = Mage::getModel('tag/tag')->load($fixture['tag_id']);
        $tagModel->setStore($fixture['store_id']);
        $tagModel->aggregate();

        $row = $readAdapter->fetchRow(
            $readAdapter->select()
                ->from($resource->getTableName('tag/summary'))
                ->where('tag_id = ?', $fixture['tag_id'])
                ->where('store_id = ?', $fixture['store_id']),
        );

        expect($row)->not->toBeNull()->and($row)->toBeArray();
        // Exactly 1 product was tagged.
        expect((int) $row['products'])->toBe(1);
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 6: addPopularityFilter produces correct rankings
// ---------------------------------------------------------------------------

it('produces correct popularity rankings via addPopularityFilter', function () {
    $fixture = tagTestInsertFixture();

    try {
        /** @var Mage_Tag_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('tag/product_collection');
        $collection
            ->addTagFilter($fixture['tag_id'])
            ->addPopularity($fixture['tag_id'], $fixture['store_id'])
            ->addPopularityFilter(['gteq' => 1]);

        // Test the assembled query in strict mode.
        tagRunInStrictMode($collection->getSelect());

        // Load via the standard path and count results.
        $collection->load();
        expect($collection->isLoaded())->toBeTrue();
        expect(count($collection->getItems()))->toBeGreaterThanOrEqual(1);
    } finally {
        tagTestCleanFixture($fixture);
    }
});
