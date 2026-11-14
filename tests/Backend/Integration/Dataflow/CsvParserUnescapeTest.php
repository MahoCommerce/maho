<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Dataflow CSV parser', function () {
    it('removes the space that the CSV export puts before a value that a spreadsheet reads as a formula', function () {
        Mage::app()->getStore()->setConfig(Mage_ImportExport_Model_Export_Adapter_Csv::CONFIG_ESCAPING_FLAG, '1');
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $profileTable = $resource->getTableName('dataflow/profile');
        $write->insert($profileTable, ['name' => 'csv-unescape-' . uniqid(), 'direction' => 'import', 'entity_type' => 'product']);
        $profileId = (int) $write->lastInsertId($profileTable);
        $batch = Mage::getSingleton('dataflow/batch')
            ->setProfileId($profileId)
            ->setAdapter('catalog/convert_adapter_product')
            ->save();
        file_put_contents(
            $batch->getIoAdapter()->getFile(true),
            "sku,name\nSKU-1,\" @home Lamp\"\nSKU-2,\" =1+1\"\nSKU-3,\" plain\"\n",
        );

        try {
            Mage::getModel('dataflow/convert_parser_csv')
                ->setVar('adapter', 'catalog/convert_adapter_product')
                ->setVar('method', 'saveRow')
                ->setVar('fieldnames', 'true')
                ->parse();

            $rows = array_map(
                fn(string $data) => unserialize($data, ['allowed_classes' => false]),
                $write->fetchCol(
                    $write->select()
                        ->from($resource->getTableName('dataflow/batch_import'), ['batch_data'])
                        ->where('batch_id = ?', (int) $batch->getId())
                        ->order('batch_import_id'),
                ),
            );

            expect($rows)->toBe([
                ['sku' => 'SKU-1', 'name' => '@home Lamp'],
                ['sku' => 'SKU-2', 'name' => '=1+1'],
                ['sku' => 'SKU-3', 'name' => ' plain'],
            ]);
        } finally {
            $batch->delete();
            $write->delete($profileTable, ['profile_id = ?' => $profileId]);
        }
    });
});
