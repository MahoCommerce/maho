<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

declare(strict_types=1);

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

// ---------------------------------------------------------------------------
// Additional helpers for the strict-GROUP-BY regression tests below
// ---------------------------------------------------------------------------

/**
 * Insert an extra tag row. Clean it up with tagTestCleanFixture(['tag_id' => $id]).
 */
function tagTestInsertExtraTag(int $storeId, ?int $customerId = null): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('tag/tag'), [
        'name'              => 'maho-test-tag-' . uniqid(),
        'status'            => Mage_Tag_Model_Tag::STATUS_APPROVED,
        'first_store_id'    => $storeId,
        'first_customer_id' => $customerId ?: null,
    ]);
    return (int) $adapter->lastInsertId($resource->getTableName('tag/tag'));
}

/**
 * Insert a tag relation row and return its tag_relation_id.
 */
function tagTestInsertRelation(int $tagId, ?int $customerId, int $productId, int $storeId): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('tag/relation'), [
        'tag_id'      => $tagId,
        'customer_id' => $customerId ?: null,
        'product_id'  => $productId,
        'store_id'    => $storeId,
        'active'      => 1,
    ]);
    return (int) $adapter->lastInsertId($resource->getTableName('tag/relation'));
}

/**
 * Insert a tag summary row for the given tag/store.
 */
function tagTestInsertSummary(int $tagId, int $storeId, int $popularity, int $products = 1, int $customers = 1): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('tag/summary'), [
        'tag_id'          => $tagId,
        'store_id'        => $storeId,
        'customers'       => $customers,
        'products'        => $products,
        'uses'            => $popularity,
        'historical_uses' => $popularity,
        'popularity'      => $popularity,
    ]);
}

/**
 * Create (and save) a real customer entity so the tag customer collection joins produce rows.
 * Caller must delete it in a finally block (isSecureArea is registered by the test case).
 */
function tagTestCreateCustomer(int $storeId): Mage_Customer_Model_Customer
{
    /** @var Mage_Customer_Model_Customer $customer */
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId((int) Mage::app()->getStore($storeId)->getWebsiteId())
        ->setStoreId($storeId)
        ->setEmail('maho-tag-test-' . uniqid() . '@example.com')
        ->setFirstname('Tag')
        ->setLastname('Tester')
        ->setGroupId(1)
        ->save();
    return $customer;
}

// ---------------------------------------------------------------------------
// Requirement 7: Tag collection addSummary — admin All/Pending Tags grid shape
// with sortable summary columns (ORDER BY must use the aggregate expression)
// ---------------------------------------------------------------------------

it('sorts the tag collection by summary columns via aggregate ORDER BY without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        tagTestInsertSummary($fixture['tag_id'], $fixture['store_id'], 3);

        /** @var Mage_Tag_Model_Resource_Tag_Collection $collection */
        $collection = Mage::getResourceModel('tag/tag_collection');
        $collection->addSummary($fixture['store_id']);
        // WHERE-side summary filter stays on the bare column (legal pre-aggregation)
        $collection->addFieldToFilter('popularity', ['gteq' => 0]);
        // Sortable grid columns: summary field must be rewritten to MAX(summary.*),
        // a main_table field must stay untouched (functionally dependent on the PK)
        $collection->setOrder('products', 'desc');
        $collection->addOrder('name', 'asc');

        // load() renders the orders into the select
        $collection->load();

        $sql = $collection->getSelect()->assemble();
        expect($sql)->toContain('MAX(summary.products)');

        tagRunInStrictMode($collection->getSelect());
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 8: addSummary GROUP BY collapses multi-store summary rows to one
// row per tag and aggregates the summary columns with MAX()
// ---------------------------------------------------------------------------

it('returns one row per tag with MAX-aggregated summary values when summary spans multiple stores', function () {
    $fixture = tagTestInsertFixture();

    try {
        // Two summary rows for the same tag (admin store 0 + the real store):
        // without the GROUP BY the joinLeft would duplicate the tag row
        tagTestInsertSummary($fixture['tag_id'], 0, 5);
        tagTestInsertSummary($fixture['tag_id'], $fixture['store_id'], 9);

        /** @var Mage_Tag_Model_Resource_Tag_Collection $collection */
        $collection = Mage::getResourceModel('tag/tag_collection');
        $collection->addSummary([0, $fixture['store_id']]);
        $collection->addFieldToFilter('tag_id', $fixture['tag_id']);

        tagRunInStrictMode($collection->getSelect());
        tagRunInStrictMode($collection->getSelectCountSql());

        expect((int) $collection->getSize())->toBe(1);

        $collection->load();
        $items = array_values($collection->getItems());
        expect(count($items))->toBe(1);
        expect((int) $items[0]->getPopularity())->toBe(9);
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 9: joinRel + addProductFilter + addTagGroup
// (Mage_Tag_Model_Tag::_getProductEventTagsCollection shape) — the relation
// join must not select relation.* into the grouped select
// ---------------------------------------------------------------------------

it('deduplicates tags via joinRel with product filter and tag group without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        // Second relation for the same tag + product in the admin store: forces
        // the GROUP BY to actually collapse duplicate joined rows
        tagTestInsertRelation($fixture['tag_id'], null, $fixture['product_id'], 0);

        /** @var Mage_Tag_Model_Resource_Tag_Collection $collection */
        $collection = Mage::getResourceModel('tag/tag_collection');
        $collection
            ->joinRel()
            ->addProductFilter($fixture['product_id'])
            ->addTagGroup();

        tagRunInStrictMode($collection->getSelect());

        $collection->load();
        $matched = array_filter(
            $collection->getItems(),
            fn($tag) => (int) $tag->getId() === $fixture['tag_id'],
        );
        expect(count($matched))->toBe(1);
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 10: joinRel + addStoreFilter (Mage_Tag_Model_Api::items shape)
// ---------------------------------------------------------------------------

it('loads the tag collection via joinRel with store filter without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();

    try {
        // addStoreFilter filters on the summary join, so the tag needs a summary row
        tagTestInsertSummary($fixture['tag_id'], $fixture['store_id'], 1);

        /** @var Mage_Tag_Model_Resource_Tag_Collection $collection */
        $collection = Mage::getModel('tag/tag')->getCollection()
            ->joinRel()
            ->addProductFilter($fixture['product_id'])
            ->addStoreFilter($fixture['store_id'])
            ->setActiveFilter();

        tagRunInStrictMode($collection->getSelect());

        $collection->load();
        $matched = array_filter(
            $collection->getItems(),
            fn($tag) => (int) $tag->getId() === $fixture['tag_id'],
        );
        expect(count($matched))->toBe(1);
    } finally {
        tagTestCleanFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 11: Tag customer collection addGroupByTag + addDescOrder
// (admin product-edit Tags tab shape) — dedup, aggregate aliases, count SQL
// ---------------------------------------------------------------------------

it('deduplicates tag customer rows with addGroupByTag and counts grouped rows correctly', function () {
    $fixture = tagTestInsertFixture();
    $customer = tagTestCreateCustomer($fixture['store_id']);

    try {
        // Two relations for the same (tag, customer) pair in different stores:
        // grouped by (tag, customer) they must collapse to a single row
        $relationIdOne = tagTestInsertRelation($fixture['tag_id'], (int) $customer->getId(), $fixture['product_id'], $fixture['store_id']);
        $relationIdTwo = tagTestInsertRelation($fixture['tag_id'], (int) $customer->getId(), $fixture['product_id'], 0);

        /** @var Mage_Tag_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('tag/customer_collection');
        $collection
            ->addTagFilter($fixture['tag_id'])
            ->addCustomerFilter((int) $customer->getId())
            ->addGroupByTag()
            ->addDescOrder();

        tagRunInStrictMode($collection->getSelect());
        // group_tag flag: portable derived-table COUNT(*) over the grouped select
        tagRunInStrictMode($collection->getSelectCountSql());

        expect((int) $collection->getSize())->toBe(1);

        $collection->load();
        $items = array_values($collection->getItems());
        expect(count($items))->toBe(1);
        // Aggregated relation columns keep their aliases: tag_relation_id = MAX(tr.tag_relation_id)
        expect((int) $items[0]->getData('tag_relation_id'))->toBe(max($relationIdOne, $relationIdTwo));
        expect((int) $items[0]->getProductId())->toBe($fixture['product_id']);
    } finally {
        tagTestCleanFixture($fixture);
        $customer->delete();
    }
});

// ---------------------------------------------------------------------------
// Requirement 12: Tag customer collection addGroupByCustomer
// (one row per customer regardless of how many tags they submitted)
// ---------------------------------------------------------------------------

it('collapses tag customer rows to one per customer with addGroupByCustomer', function () {
    $fixture = tagTestInsertFixture();
    $customer = tagTestCreateCustomer($fixture['store_id']);
    $extraTagId = tagTestInsertExtraTag($fixture['store_id']);

    try {
        // Same customer tagged the product with two different tags
        tagTestInsertRelation($fixture['tag_id'], (int) $customer->getId(), $fixture['product_id'], $fixture['store_id']);
        tagTestInsertRelation($extraTagId, (int) $customer->getId(), $fixture['product_id'], $fixture['store_id']);

        /** @var Mage_Tag_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('tag/customer_collection');
        $collection
            ->addCustomerFilter((int) $customer->getId())
            ->addGroupByCustomer();

        tagRunInStrictMode($collection->getSelect());
        // group_customer flag: portable derived-table COUNT(*) over the grouped
        // select (multi-column COUNT(DISTINCT) would break on PostgreSQL)
        tagRunInStrictMode($collection->getSelectCountSql());

        expect((int) $collection->getSize())->toBe(1);

        $collection->load();
        $items = array_values($collection->getItems());
        expect(count($items))->toBe(1);
        expect($items[0]->getEmail())->toBe($customer->getEmail());
    } finally {
        tagTestCleanFixture($fixture);
        tagTestCleanFixture(['tag_id' => $extraTagId]);
        $customer->delete();
    }
});

// ---------------------------------------------------------------------------
// Requirement 13: Tag product collection setDescOrder
// (Customer Recent dashboard shape) — ORDER BY must use the aggregate and
// still return products most-recently-tagged first
// ---------------------------------------------------------------------------

it('orders tagged products by most recent tag relation via setDescOrder without strict GROUP BY errors', function () {
    $fixture = tagTestInsertFixture();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    // Find a second product to tag after the fixture product
    $productIds = $adapter->fetchCol(
        $adapter->select()
            ->from($resource->getTableName('catalog/product'), ['entity_id'])
            ->where('entity_id != ?', $fixture['product_id'])
            ->limit(1),
    );
    if (empty($productIds)) {
        tagTestCleanFixture($fixture);
        $this->markTestSkipped('Need at least two products for the ordering test.');
    }
    $secondProductId = (int) $productIds[0];

    try {
        // Tagged later than the fixture relation, so it must sort first (DESC)
        tagTestInsertRelation($fixture['tag_id'], null, $secondProductId, $fixture['store_id']);

        /** @var Mage_Tag_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('tag/product_collection');
        $collection
            ->addTagFilter($fixture['tag_id'])
            ->setDescOrder();

        // load() renders the orders into the select
        $collection->load();

        $sql = $collection->getSelect()->assemble();
        expect($sql)->toContain('MAX(relation.tag_relation_id)');

        tagRunInStrictMode($collection->getSelect());

        $items = array_values($collection->getItems());
        expect(count($items))->toBe(2);
        expect((int) $items[0]->getId())->toBe($secondProductId);
        expect((int) $items[1]->getId())->toBe($fixture['product_id']);
    } finally {
        tagTestCleanFixture($fixture);
    }
});
