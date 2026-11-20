<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('saves a stored advanced profile without wizard data', function (?string $guiData): void {
    $resource = Mage::getResourceSingleton('dataflow/profile');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = $resource->getMainTable();
    $name = uniqid('Advanced profile ');
    $actions = '<action type="dataflow/convert_adapter_http" method="save"></action>';

    $resource->beginTransaction();
    try {
        $adapter->insert($table, [
            'name' => $name,
            'actions_xml' => $actions,
            'gui_data' => $guiData,
            'entity_type' => null,
        ]);
        $id = (int) $adapter->lastInsertId($table);
        expect($id)->toBeGreaterThan(0);
        $profile = Mage::getModel('dataflow/profile')->load($id);
        expect((int) $profile->getId())->toBe($id);

        $profile->setName($name . ' edited')->save();

        $saved = Mage::getModel('dataflow/profile')->load($id);
        expect($saved->getName())->toBe($name . ' edited')
            ->and($saved->getActionsXml())->toBe($actions);
    } finally {
        $resource->rollBack();
    }
})->with([
    'empty string' => [''],
    'SQL NULL' => [null],
]);
