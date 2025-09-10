<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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

    it('handles missing columns in rows gracefully', function () {
        $data = [
            ['name' => 'John', 'email' => 'john@example.com', 'age' => '30'],
            ['name' => 'Jane', 'email' => 'jane@example.com'], // Missing 'age'
        ];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);

        expect($adapter->getRowCount())->toBe(2);
        expect($adapter->validateSource())->toBe($adapter);
    });
});

describe('Import Model with Array Adapter', function () {
    it('can import products from array', function () {
        $productData = [
            [
                'sku' => 'TEST-PRODUCT-001',
                'name' => 'Test Product 1',
                'product_type' => 'simple',
                'attribute_set' => 'Default',
                'price' => '10.00',
                'status' => '1',
                'visibility' => '4',
                'tax_class_id' => '2',
                'weight' => '1.0000',
                'qty' => '100',
                'is_in_stock' => '1',
            ],
            [
                'sku' => 'TEST-PRODUCT-002',
                'name' => 'Test Product 2',
                'product_type' => 'simple',
                'attribute_set' => 'Default',
                'price' => '15.00',
                'status' => '1',
                'visibility' => '4',
                'tax_class_id' => '2',
                'weight' => '2.0000',
                'qty' => '50',
                'is_in_stock' => '1',
            ],
        ];

        $import = Mage::getModel('importexport/import');

        // Test validation
        $import->setData([
            'entity' => 'catalog_product',
            'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
        ]);

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($productData);
        expect($adapter)->toBeInstanceOf(Mage_ImportExport_Model_Import_Adapter_Array::class);

        // Test that adapter can be used with entity adapter
        $entityAdapter = $import->getEntityAdapter();
        expect($entityAdapter->setSource($adapter))->toBe($entityAdapter);
    });

    it('validates entity types for array import', function () {
        $data = [
            ['name' => 'Test', 'email' => 'test@example.com'],
        ];

        $import = Mage::getModel('importexport/import');

        expect(fn() => $import->importFromArray($data, 'invalid_entity'))
            ->toThrow(Mage_Core_Exception::class);
    });

    it('supports different import behaviors', function () {
        $data = [
            ['sku' => 'TEST-SKU', 'name' => 'Test Product'],
        ];

        $import = Mage::getModel('importexport/import');

        // Should not throw exception for valid behaviors
        expect($import->setData([
            'entity' => 'catalog_product',
            'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
        ]))->toBe($import);

        expect($import->setData([
            'entity' => 'catalog_product',
            'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE,
        ]))->toBe($import);

        expect($import->setData([
            'entity' => 'catalog_product',
            'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_DELETE,
        ]))->toBe($import);
    });
});

describe('Array Adapter Factory Methods', function () {
    it('creates correct adapter type from factory', function () {
        $data = [['name', 'email'], ['John', 'john@example.com']];

        $adapter = Mage_ImportExport_Model_Import_Adapter::factory('array', $data);
        expect($adapter)->toBeInstanceOf(Mage_ImportExport_Model_Import_Adapter_Array::class);
    });

    it('creates adapter via createArrayAdapter helper', function () {
        $data = [['name', 'email'], ['John', 'john@example.com']];

        $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);
        expect($adapter)->toBeInstanceOf(Mage_ImportExport_Model_Import_Adapter_Array::class);
    });

    it('maintains file adapter functionality', function () {
        // Test that existing file-based adapters still work
        expect(fn() => Mage_ImportExport_Model_Import_Adapter::factory('csv', 'nonexistent.csv'))
            ->toThrow(Mage_Core_Exception::class);
    });
});
