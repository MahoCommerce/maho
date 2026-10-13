<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('loads the rows of an entity type and saves a changed row', function () {
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getWebsite(true)->getId())
        ->setGroupId(1)
        ->setFirstname('Grid')
        ->setLastname('Adapter')
        ->setEmail('grid-adapter-' . uniqid() . '@example.test')
        ->save();

    try {
        $adapter = Mage::getModel('eav/convert_adapter_grid')->setVar('entity_type', 'customer/customer');
        $ids = array_map(intval(...), array_column($adapter->load()->getData(), 'entity_id'));

        expect($ids)->toContain((int) $customer->getId());

        $adapter->setData([['entity_id' => $customer->getId(), 'firstname' => 'Changed']])->save();

        expect(Mage::getModel('customer/customer')->load($customer->getId())->getFirstname())->toBe('Changed');
    } finally {
        $customer->delete();
    }
});

it('records an error and saves nothing for an unknown entity type', function () {
    $adapter = Mage::getModel('eav/convert_adapter_grid');
    $adapter->setData([['firstname' => 'Nobody']]);

    expect($adapter->save())->toBe($adapter)
        ->and($adapter->getEntity())->toBeNull();
});
