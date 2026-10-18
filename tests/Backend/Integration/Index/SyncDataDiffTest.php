<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function syncDataResource(): Mage_Index_Model_Resource_Abstract
{
    $resource = new class extends Mage_Index_Model_Resource_Abstract {
        #[\Override]
        protected function _construct()
        {
            $this->_init('catalog/product_index_price', 'entity_id');
        }
    };
    $resource->useIdxTable(true);
    return $resource;
}

/** @return list<array<string, mixed>> */
function syncDataRows(string $table): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    return $adapter->fetchAll(
        $adapter->select()->from($table)->order(['entity_id', 'customer_group_id', 'website_id']),
    );
}

/** @param list<array<string, mixed>> $rows */
function syncDataFill(string $table, array $rows): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete($table);
    foreach (array_chunk($rows, 500) as $chunk) {
        $adapter->insertMultiple($table, $chunk);
    }
}

beforeEach(function () {
    $this->resource = syncDataResource();
    $this->mainTable = $this->resource->getMainTable();
    $this->idxTable = $this->resource->getIdxTable();
    $this->mainRows = syncDataRows($this->mainTable);
    $this->idxRows = syncDataRows($this->idxTable);
    if (count($this->mainRows) < 4) {
        $this->markTestSkipped('The price index needs at least 4 rows');
    }
});

afterEach(function () {
    syncDataFill($this->mainTable, $this->mainRows);
    syncDataFill($this->idxTable, $this->idxRows);
});

it('makes the main table equal to the index table', function () {
    $rows = $this->mainRows;
    $removed = array_shift($rows);
    $rows[0]['tier_price'] = $rows[0]['tier_price'] === null ? '5.0000' : null;
    $rows[1]['group_price'] = '7.0000';
    $rows[2]['price'] = (string) ((float) $rows[2]['price'] + 1);
    syncDataFill($this->idxTable, $rows);

    $missing = $rows[3];
    Mage::getSingleton('core/resource')->getConnection('core_write')->delete($this->mainTable, [
        'entity_id = ?' => $missing['entity_id'],
        'customer_group_id = ?' => $missing['customer_group_id'],
        'website_id = ?' => $missing['website_id'],
    ]);

    $this->resource->syncData();

    expect(syncDataRows($this->mainTable))->toEqual(syncDataRows($this->idxTable))
        ->and(syncDataRows($this->mainTable))->not->toContain($removed);
});

it('changes a NULL to a value and back again', function () {
    $rows = $this->mainRows;
    $rows[0]['tier_price'] = '3.0000';
    syncDataFill($this->idxTable, $rows);
    $this->resource->syncData();
    expect(syncDataRows($this->mainTable)[0]['tier_price'])->toEqual('3.0000');

    $rows[0]['tier_price'] = null;
    syncDataFill($this->idxTable, $rows);
    $this->resource->syncData();
    expect(syncDataRows($this->mainTable)[0]['tier_price'])->toBeNull();
});

it('compares NULL with NULL as equal and NULL with a value as different', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $sql = fn(string $left, string $right): string => 'SELECT CASE WHEN '
        . $adapter->getNullSafeEqualsSql($left, $right) . ' THEN 1 ELSE 0 END';

    expect((int) $adapter->fetchOne($sql('NULL', 'NULL')))->toBe(1)
        ->and((int) $adapter->fetchOne($sql('NULL', '1')))->toBe(0)
        ->and((int) $adapter->fetchOne($sql('1', '1')))->toBe(1)
        ->and((int) $adapter->fetchOne($sql('1', '2')))->toBe(0);
});
