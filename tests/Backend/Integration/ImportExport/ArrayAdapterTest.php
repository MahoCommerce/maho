<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('ImportExport Array Adapter', function () {
    beforeEach(function () {
        $this->adapter = null;
    });

    it('can create array adapter instance', function () {
        $data = [
            ['name', 'email', 'age'],
            ['John Doe', 'john@example.com', '30'],
            ['Jane Smith', 'jane@example.com', '25'],
        ];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);

        expect($adapter)->toBeInstanceOf(Mage_ImportExport_Model_Import_Adapter_Array::class);
        expect($adapter->getColNames())->toBe(['name', 'email', 'age']);
        expect($adapter->getRowCount())->toBe(2);
    });

    it('handles associative arrays correctly', function () {
        $data = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => '30'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => '25'],
        ];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);

        expect($adapter->getColNames())->toBe(['name', 'email', 'age']);
        expect($adapter->getRowCount())->toBe(2);
    });

    it('iterates through data correctly', function () {
        $data = [
            ['name', 'email'],
            ['John', 'john@example.com'],
            ['Jane', 'jane@example.com'],
        ];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);
        $rows = [];

        foreach ($adapter as $row) {
            $rows[] = $row;
        }

        expect(count($rows))->toBe(2);
        expect($rows[0])->toBe(['name' => 'John', 'email' => 'john@example.com']);
        expect($rows[1])->toBe(['name' => 'Jane', 'email' => 'jane@example.com']);
    });

    it('supports seeking to specific positions', function () {
        $data = [
            ['name', 'email'],
            ['First', 'first@example.com'],
            ['Second', 'second@example.com'],
            ['Third', 'third@example.com'],
        ];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);

        $adapter->seek(1);
        expect($adapter->current())->toBe(['name' => 'Second', 'email' => 'second@example.com']);

        $adapter->seek(0);
        expect($adapter->current())->toBe(['name' => 'First', 'email' => 'first@example.com']);
    });

    it('throws exception for invalid seek position', function () {
        $data = [
            ['name', 'email'],
            ['John', 'john@example.com'],
        ];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);

        expect(fn() => $adapter->seek(5))->toThrow(OutOfBoundsException::class);
        expect(fn() => $adapter->seek(-1))->toThrow(OutOfBoundsException::class);
    });

    it('throws exception for empty data', function () {
        expect(fn() => Mage_ImportExport_Model_Import_Adapter::createArrayAdapter([]))
            ->toThrow(Mage_Core_Exception::class, 'Source data array cannot be empty');
    });

    it('throws exception for non-array data', function () {
        expect(fn() => Mage_ImportExport_Model_Import_Adapter::createArrayAdapter('not an array'))
            ->toThrow(Mage_Core_Exception::class, 'Source data must be an array');
    });

    it('validates column name duplicates', function () {
        $data = [
            ['name', 'name', 'email'], // Duplicate column names
            ['John', 'Doe', 'john@example.com'],
        ];

        expect(fn() => Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data))
            ->toThrow(Mage_Core_Exception::class, 'Column names have duplicates');
    });

    it('integrates with import pipeline', function () {
        $data = [
            ['sku' => 'TEST-SKU', 'name' => 'Test Product'],
        ];

        $import = Mage::getModel('importexport/import');
        $import->setData([
            'entity' => 'catalog_product',
            'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
        ]);

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);
        $entityAdapter = $import->getEntityAdapter();

        expect($entityAdapter->setSource($adapter))->toBe($entityAdapter);
    });
});
