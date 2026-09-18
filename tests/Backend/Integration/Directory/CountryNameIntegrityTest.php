<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('has a country row for every country name', function (): void {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $select = $adapter->select()
        ->from(['n' => $resource->getTableName('directory/country_name')], ['country_id'])
        ->joinLeft(['c' => $resource->getTableName('directory/country')], 'c.country_id = n.country_id', [])
        ->where('c.country_id IS NULL');

    expect($adapter->fetchCol($select))->toBe([]);
});
