<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Db
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const DELETE_FROM_SELECT_ENTITY = 999999901;

function deleteFromSelectTable(): string
{
    return Mage::getSingleton('core/resource')->getTableName('catalog/product_index_eav') . '_tmp';
}

/** @return list<int> the store ids that are left for the test entity */
function deleteFromSelectStores(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    return array_map('intval', $adapter->fetchCol(
        $adapter->select()
            ->from(deleteFromSelectTable(), ['store_id'])
            ->where('entity_id = ?', DELETE_FROM_SELECT_ENTITY)
            ->order('store_id'),
    ));
}

beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete(deleteFromSelectTable(), ['entity_id = ?' => DELETE_FROM_SELECT_ENTITY]);
    $adapter->insertMultiple(deleteFromSelectTable(), [
        ['entity_id' => DELETE_FROM_SELECT_ENTITY, 'attribute_id' => 1, 'store_id' => 1, 'value' => 1],
        ['entity_id' => DELETE_FROM_SELECT_ENTITY, 'attribute_id' => 1, 'store_id' => 2, 'value' => 1],
    ]);
});

afterEach(function () {
    Mage::getSingleton('core/resource')->getConnection('core_write')
        ->delete(deleteFromSelectTable(), ['entity_id = ?' => DELETE_FROM_SELECT_ENTITY]);
});

it('deletes only the rows whose whole primary key matches', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $select = $adapter->select()
        ->from(['i' => deleteFromSelectTable()], [])
        ->where('i.entity_id = ?', DELETE_FROM_SELECT_ENTITY)
        ->where('i.store_id = ?', 1);

    $adapter->query($adapter->deleteFromSelect($select, 'i'));

    expect(deleteFromSelectStores())->toBe([2]);
});
