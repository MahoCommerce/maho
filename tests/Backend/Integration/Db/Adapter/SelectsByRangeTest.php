<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('splits a select into ranges of its main table', function (): void {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $select = $adapter->select()->from(['s' => $resource->getTableName('core/store')], ['store_id']);

    $selects = $adapter->selectsByRange('store_id', $select, 1000);

    expect($selects)->toHaveCount(1)
        ->and($adapter->fetchCol($selects[0]))->toEqualCanonicalizing($adapter->fetchCol($select));
});
