<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class BatchEventsSpyPriceIndexer extends Mage_Catalog_Model_Product_Indexer_Price
{
    /** @var list<list<int>> */
    public array $batches = [];

    #[\Override]
    public function processBatch(array $entityIds): void
    {
        $this->batches[] = $entityIds;
        parent::processBatch($entityIds);
    }
}

function batchEventsProcess(): Mage_Index_Model_Process
{
    /** @var Mage_Index_Model_Process $process */
    $process = Mage::getModel('index/process')->load('catalog_product_price', 'indexer_code');
    new ReflectionProperty($process, '_indexer')->setValue($process, new BatchEventsSpyPriceIndexer());
    return $process;
}

function batchEventsLog(Mage_Index_Model_Process $process, string $type, int $productId): Mage_Index_Model_Event
{
    /** @var Mage_Index_Model_Event $event */
    $event = Mage::getModel('index/event')
        ->setEntity(Mage_Catalog_Model_Product::ENTITY)
        ->setType($type)
        ->setEntityPk($productId)
        ->setNewData([BatchEventsSpyPriceIndexer::class => ['reindex_price' => 1, 'product_type_id' => 'simple']]);
    $event->addProcessId((int) $process->getId(), Mage_Index_Model_Process::EVENT_STATUS_NEW);
    $event->save();
    return $event;
}

function batchEventsPriceRows(int $productId): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_index_price'), [new Maho\Db\Expr('COUNT(*)')])
            ->where('entity_id = ?', $productId),
    );
}

function batchEventsPending(Mage_Index_Model_Process $process): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('index/process_event'), [new Maho\Db\Expr('COUNT(*)')])
            ->where('process_id = ?', (int) $process->getId())
            ->where('status = ?', Mage_Index_Model_Process::EVENT_STATUS_NEW),
    );
}

beforeEach(function () {
    $this->products = [
        createPriceWebsiteProduct('batch-events-a', 11.0),
        createPriceWebsiteProduct('batch-events-b', 12.0),
        createPriceWebsiteProduct('batch-events-c', 13.0),
    ];
    $this->ids = array_map(fn(Mage_Catalog_Model_Product $product): int => (int) $product->getId(), $this->products);

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete($resource->getTableName('catalog/product_index_price'), ['entity_id IN(?)' => $this->ids]);
    // A logged event merges into an older row of the same product, which keeps its older id
    $adapter->delete($resource->getTableName('index/event'), [
        'entity = ?' => Mage_Catalog_Model_Product::ENTITY,
        'entity_pk IN(?)' => $this->ids,
    ]);
});

afterEach(function () {
    foreach ($this->products as $product) {
        $product->delete();
    }
});

it('reindexes the products of pending save events in one batch', function () {
    $process = batchEventsProcess();
    foreach ($this->ids as $id) {
        batchEventsLog($process, Mage_Index_Model_Event::TYPE_SAVE, $id);
    }

    $process->indexEvents(Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);

    $batches = $process->getIndexer()->batches;
    sort($batches[0]);
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBe($this->ids)
        ->and(batchEventsPending($process))->toBe(0);
    foreach ($this->ids as $id) {
        expect(batchEventsPriceRows($id))->toBeGreaterThan(0);
    }
});

it('processes an event that cannot join a batch after the events before it', function () {
    $process = batchEventsProcess();
    batchEventsLog($process, Mage_Index_Model_Event::TYPE_SAVE, $this->ids[0]);
    batchEventsLog($process, Mage_Index_Model_Event::TYPE_DELETE, $this->ids[1]);
    batchEventsLog($process, Mage_Index_Model_Event::TYPE_SAVE, $this->ids[2]);

    $process->indexEvents(Mage_Catalog_Model_Product::ENTITY);

    expect($process->getIndexer()->batches)->toBe([[$this->ids[0]], [$this->ids[2]]])
        ->and(batchEventsPending($process))->toBe(0);
});

it('processes the events of an indexer without batch support one by one', function () {
    expect(Mage::getSingleton('index/indexer')->getProcessByCode('tag_summary')->getIndexer()->supportsBatchEvents())
        ->toBeFalse();
});
