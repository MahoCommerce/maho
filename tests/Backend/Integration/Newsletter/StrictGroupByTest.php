<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for the strict-GROUP-BY fix in
 * Mage_Newsletter_Model_Resource_Queue_Collection::_getIdsFromLink().
 *
 * The HAVING clause previously referenced the 'total' SELECT alias
 * (HAVING total >= X) — a MySQL-only extension rejected by PostgreSQL and by
 * MySQL under ONLY_FULL_GROUP_BY-adjacent strictness. The fix references the
 * underlying aggregate directly: HAVING (COUNT(queue_link_id) >= X).
 *
 * Note on strict-mode enforcement: MahoBackendTestCase::setUp() opens the DB
 * connection before beforeEach() runs, so on MySQL/MariaDB the live adapter may
 * predate developer mode. To guarantee ONLY_FULL_GROUP_BY is active we swap a
 * freshly created MySQL adapter (opened after Mage::setIsDeveloperMode(true))
 * into the collection via reflection — _getIdsFromLink() builds and executes
 * its query through $this->getConnection(), so the swap exercises the exact
 * production code path under strict mode. On PostgreSQL strict GROUP BY is
 * always enforced natively; on SQLite the behavioral assertions still run,
 * verifying the query stays engine-portable.
 */

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Return an adapter with strict GROUP BY guaranteed active.
 * On MySQL/MariaDB: a fresh connection opened under developer mode
 * (ONLY_FULL_GROUP_BY). On PostgreSQL/SQLite: the live adapter.
 */
function newsletterStrictAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    if ($liveAdapter instanceof Mysql) {
        Mage::setIsDeveloperMode(true);
        return new Mysql($liveAdapter->getConfig());
    }

    return $liveAdapter;
}

/**
 * Create a newsletter queue collection whose connection is the strict adapter,
 * without resetting the already-initialized main select.
 */
function newsletterStrictQueueCollection(): Mage_Newsletter_Model_Resource_Queue_Collection
{
    /** @var Mage_Newsletter_Model_Resource_Queue_Collection $collection */
    $collection = Mage::getResourceModel('newsletter/queue_collection');

    $property = new \ReflectionProperty(\Maho\Data\Collection\Db::class, '_conn');
    $property->setValue($collection, newsletterStrictAdapter());

    return $collection;
}

/**
 * Insert minimal newsletter fixtures:
 * - one template
 * - queue A with 3 queue_link rows (2 sent, 1 unsent)
 * - queue B with 1 queue_link row (unsent)
 *
 * Returns ['template_id', 'queue_a_id', 'queue_b_id', 'subscriber_ids'].
 */
function newsletterInsertQueueFixture(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('newsletter/template'), [
        'template_code'         => 'maho-test-groupby-' . uniqid(),
        'template_subject'      => 'Strict GROUP BY test',
        'template_sender_name'  => 'Maho Test',
        'template_sender_email' => 'test@example.com',
        'template_type'         => Mage_Newsletter_Model_Template::TYPE_HTML,
        'template_text'         => 'test',
    ]);
    $templateId = (int) $adapter->lastInsertId($resource->getTableName('newsletter/template'));

    $queueIds = [];
    foreach (['A', 'B'] as $label) {
        $adapter->insert($resource->getTableName('newsletter/queue'), [
            'template_id'        => $templateId,
            'newsletter_subject' => 'Strict GROUP BY queue ' . $label,
            'newsletter_text'    => 'test',
            'queue_status'       => Mage_Newsletter_Model_Queue::STATUS_NEVER,
        ]);
        $queueIds[$label] = (int) $adapter->lastInsertId($resource->getTableName('newsletter/queue'));
    }

    $subscriberIds = [];
    for ($i = 0; $i < 2; $i++) {
        $adapter->insert($resource->getTableName('newsletter/subscriber'), [
            'store_id'          => 0,
            'customer_id'       => 0,
            'subscriber_email'  => 'maho-groupby-' . uniqid() . '@example.com',
            'subscriber_status' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
        ]);
        $subscriberIds[] = (int) $adapter->lastInsertId($resource->getTableName('newsletter/subscriber'));
    }

    // Queue A: total = 3, sent = 2
    $linkRows = [
        ['queue_id' => $queueIds['A'], 'subscriber_id' => $subscriberIds[0], 'letter_sent_at' => '2025-01-01 10:00:00'],
        ['queue_id' => $queueIds['A'], 'subscriber_id' => $subscriberIds[1], 'letter_sent_at' => '2025-01-01 10:05:00'],
        ['queue_id' => $queueIds['A'], 'subscriber_id' => $subscriberIds[1], 'letter_sent_at' => null],
        // Queue B: total = 1, sent = 0
        ['queue_id' => $queueIds['B'], 'subscriber_id' => $subscriberIds[0], 'letter_sent_at' => null],
    ];
    foreach ($linkRows as $row) {
        $adapter->insert($resource->getTableName('newsletter/queue_link'), $row);
    }

    return [
        'template_id'    => $templateId,
        'queue_a_id'     => $queueIds['A'],
        'queue_b_id'     => $queueIds['B'],
        'subscriber_ids' => $subscriberIds,
    ];
}

/**
 * Remove newsletter fixture data (links, queues, template, subscribers).
 */
function newsletterCleanQueueFixture(array $fixture): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $queueIds = [$fixture['queue_a_id'], $fixture['queue_b_id']];
    $adapter->delete($resource->getTableName('newsletter/queue_link'), ['queue_id IN (?)' => $queueIds]);
    $adapter->delete($resource->getTableName('newsletter/queue'), ['queue_id IN (?)' => $queueIds]);
    $adapter->delete($resource->getTableName('newsletter/template'), ['template_id = ?' => $fixture['template_id']]);
    foreach ($fixture['subscriber_ids'] as $subscriberId) {
        $adapter->delete($resource->getTableName('newsletter/subscriber'), ['subscriber_id = ?' => $subscriberId]);
    }
}

// ---------------------------------------------------------------------------
// Requirement 1: subscribers_total filter (public path through _getIdsFromLink)
// ---------------------------------------------------------------------------

it('filters the queue collection by subscribers_total under strict GROUP BY and returns matching queues', function () {
    $fixture = newsletterInsertQueueFixture();

    try {
        // addFieldToFilter('subscribers_total', ...) calls _getIdsFromLink(),
        // which builds and executes the GROUP BY / HAVING query through the
        // (strict) collection connection. A GROUP BY violation throws here.
        $collection = newsletterStrictQueueCollection();
        $collection->addFieldToFilter('subscribers_total', ['gteq' => 2]);
        $collection->load();

        $queueIds = array_map('intval', array_keys($collection->getItems()));
        expect($queueIds)->toContain($fixture['queue_a_id']);   // 3 links
        expect($queueIds)->not->toContain($fixture['queue_b_id']); // 1 link
    } finally {
        newsletterCleanQueueFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 2: subscribers_sent filter (adds letter_sent_at IS NOT NULL)
// ---------------------------------------------------------------------------

it('filters the queue collection by subscribers_sent under strict GROUP BY and returns matching queues', function () {
    $fixture = newsletterInsertQueueFixture();

    try {
        $collection = newsletterStrictQueueCollection();
        $collection->addFieldToFilter('subscribers_sent', ['gteq' => 1]);
        $collection->load();

        $queueIds = array_map('intval', array_keys($collection->getItems()));
        expect($queueIds)->toContain($fixture['queue_a_id']);   // 2 sent
        expect($queueIds)->not->toContain($fixture['queue_b_id']); // 0 sent
    } finally {
        newsletterCleanQueueFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 3: _getIdsFromLink direct — exact aggregate counts per queue
// ---------------------------------------------------------------------------

it('returns correct queue ids from _getIdsFromLink for exact aggregate counts', function () {
    $fixture = newsletterInsertQueueFixture();

    try {
        $collection = newsletterStrictQueueCollection();
        $method = new \ReflectionMethod($collection, '_getIdsFromLink');

        // Queue A has exactly 3 links, queue B exactly 1.
        $idsTotal3 = array_map('intval', $method->invoke($collection, 'subscribers_total', ['eq' => 3]));
        expect($idsTotal3)->toContain($fixture['queue_a_id'])
            ->and($idsTotal3)->not->toContain($fixture['queue_b_id']);

        $idsTotal1 = array_map('intval', $method->invoke($collection, 'subscribers_total', ['eq' => 1]));
        expect($idsTotal1)->toContain($fixture['queue_b_id'])
            ->and($idsTotal1)->not->toContain($fixture['queue_a_id']);

        // Queue A has exactly 2 sent links; queue B has none (filtered out by
        // the letter_sent_at IS NOT NULL predicate before grouping).
        $idsSent2 = array_map('intval', $method->invoke($collection, 'subscribers_sent', ['eq' => 2]));
        expect($idsSent2)->toContain($fixture['queue_a_id'])
            ->and($idsSent2)->not->toContain($fixture['queue_b_id']);
    } finally {
        newsletterCleanQueueFixture($fixture);
    }
});

// ---------------------------------------------------------------------------
// Requirement 4: _getIdsFromLink no-match fallback returns [0]
// ---------------------------------------------------------------------------

it('returns the [0] fallback from _getIdsFromLink when no queue matches the condition', function () {
    $collection = newsletterStrictQueueCollection();
    $method = new \ReflectionMethod($collection, '_getIdsFromLink');

    $ids = $method->invoke($collection, 'subscribers_total', ['gteq' => PHP_INT_MAX]);
    expect($ids)->toBe([0]);

    // The public filter path with an impossible condition must load empty,
    // not fail — the fallback keeps the IN () clause valid.
    $emptyCollection = newsletterStrictQueueCollection();
    $emptyCollection->addFieldToFilter('subscribers_total', ['gteq' => PHP_INT_MAX]);
    $emptyCollection->load();
    expect($emptyCollection->getItems())->toBe([]);
});
