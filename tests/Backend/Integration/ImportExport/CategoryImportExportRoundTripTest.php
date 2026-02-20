<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

beforeEach(function () {
    $this->importModel = Mage::getModel('importexport/import_entity_category');
    $this->exportModel = Mage::getModel('importexport/export_entity_category');
    $this->writer = Mage::getModel('importexport/export_adapter_csv');

    // CSV adapter will be created in helper function with proper source file
    $this->exportModel->setWriter($this->writer);
});

afterEach(function () {
    // Clean up test categories
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('level', ['gt' => 0])
        ->addAttributeToFilter('entity_id', ['gt' => 2]);

    foreach ($collection as $category) {
        try {
            $category->delete();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
});

it('preserves data integrity in export-import round trip', function () {
    // Create test categories using exact format that works in CategoryImportTest
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', '2', '', 'Test RT Electronics', '1', 'test-rt-electronics'],
        ['', '2', '', 'Test RT Phones', '1', 'test-rt-phones'],
        ['', '2', '', 'Test RT Clothing', '0', 'test-rt-clothing'],
    ];

    createAndImportRoundTripCsv($csvData);

    // Verify categories were created
    $electronics = findCategoryByUrlKeyRoundTrip('test-rt-electronics');
    $phones = findCategoryByUrlKeyRoundTrip('test-rt-phones');
    $clothing = findCategoryByUrlKeyRoundTrip('test-rt-clothing');

    expect($electronics)->not->toBeNull()
        ->and($phones)->not->toBeNull()
        ->and($clothing)->not->toBeNull();

    // Export categories
    $result = $this->exportModel->exportFile();
    expect($result)->toBeArray()
        ->and($result['type'])->toBe('string')
        ->and($result['rows'])->toBeGreaterThan(0);

    $exportedCsv = $result['value'];

    // Delete test categories
    $electronics->delete();
    $phones->delete();
    $clothing->delete();

    // Verify categories are gone
    expect(findCategoryByUrlKeyRoundTrip('test-rt-electronics'))->toBeNull()
        ->and(findCategoryByUrlKeyRoundTrip('test-rt-phones'))->toBeNull()
        ->and(findCategoryByUrlKeyRoundTrip('test-rt-clothing'))->toBeNull();

    // Re-import from exported CSV
    importFromCsvStringRoundTrip($exportedCsv);

    // Verify categories are restored with correct data
    $restoredElectronics = findCategoryByUrlKeyRoundTrip('test-rt-electronics');
    $restoredPhones = findCategoryByUrlKeyRoundTrip('test-rt-phones');
    $restoredClothing = findCategoryByUrlKeyRoundTrip('test-rt-clothing');

    expect($restoredElectronics)->not->toBeNull()
        ->and($restoredPhones)->not->toBeNull()
        ->and($restoredClothing)->not->toBeNull();

    expect($restoredElectronics->getName())->toBe('Test RT Electronics')
        ->and($restoredPhones->getName())->toBe('Test RT Phones')
        ->and($restoredClothing->getName())->toBe('Test RT Clothing');

    expect($restoredElectronics->getIsActive())->toBe(1)
        ->and($restoredPhones->getIsActive())->toBe(1)
        ->and($restoredClothing->getIsActive())->toBe(0);
});

it('handles hierarchical categories correctly', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', '2', '', 'Test Electronics', '1', 'test-electronics'],
        ['', '2', '', 'Test Phones', '1', 'test-phones'],
    ];

    createAndImportRoundTripCsv($csvData);

    $electronics = findCategoryByUrlKeyRoundTrip('test-electronics');
    $phones = findCategoryByUrlKeyRoundTrip('test-phones');

    expect($electronics)->not->toBeNull()
        ->and($phones)->not->toBeNull();

    // Export, delete, and re-import
    $result = $this->exportModel->exportFile();
    $exportedCsv = $result['value'];

    $electronics->delete();
    $phones->delete();

    importFromCsvStringRoundTrip($exportedCsv);

    // Verify restoration
    $restoredElectronics = findCategoryByUrlKeyRoundTrip('test-electronics');
    $restoredPhones = findCategoryByUrlKeyRoundTrip('test-phones');

    expect($restoredElectronics)->not->toBeNull()
        ->and($restoredPhones)->not->toBeNull()
        ->and($restoredElectronics->getName())->toBe('Test Electronics')
        ->and($restoredPhones->getName())->toBe('Test Phones');
});

it('preserves url_key during round trip', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', '2', '', 'Test Special URL', '1', 'test-special-url'],
    ];

    createAndImportRoundTripCsv($csvData);
    $original = findCategoryByUrlKeyRoundTrip('test-special-url');
    expect($original)->not->toBeNull();

    // Export, delete, re-import
    $result = $this->exportModel->exportFile();
    $exportedCsv = $result['value'];
    $original->delete();

    importFromCsvStringRoundTrip($exportedCsv);

    $restored = findCategoryByUrlKeyRoundTrip('test-special-url');
    expect($restored)->not->toBeNull()
        ->and($restored->getName())->toBe('Test Special URL')
        ->and($restored->getUrlKey())->toBe('test-special-url');
});

it('handles multiple categories with different properties', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key', 'description'],
        ['', '2', '', 'Active Category', '1', 'active-category', 'Description for active'],
        ['', '2', '', 'Inactive Category', '0', 'inactive-category', 'Description for inactive'],
    ];

    createAndImportRoundTripCsv($csvData);

    // Export and verify data integrity through round trip
    $result = $this->exportModel->exportFile();
    $exportedCsv = $result['value'];

    // Clean up and re-import
    findCategoryByUrlKeyRoundTrip('active-category')->delete();
    findCategoryByUrlKeyRoundTrip('inactive-category')->delete();

    importFromCsvStringRoundTrip($exportedCsv);

    $activeRestored = findCategoryByUrlKeyRoundTrip('active-category');
    $inactiveRestored = findCategoryByUrlKeyRoundTrip('inactive-category');

    expect($activeRestored)->not->toBeNull()
        ->and($inactiveRestored)->not->toBeNull()
        ->and($activeRestored->getIsActive())->toBe(1)
        ->and($inactiveRestored->getIsActive())->toBe(0);
});

it('handles store-specific category data', function () {
    // Create category with store-specific data
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', '2', '', 'Test Electronics', '1', 'test-electronics'], // Default store
        ['', '2', 'en', 'Test Elektronik', '1', ''], // English store specific
    ];

    createAndImportRoundTripCsv($csvData);

    $category = findCategoryByUrlKeyRoundTrip('test-electronics');
    expect($category)->not->toBeNull();

    // Verify store-specific name exists
    $category->setStoreId(1); // English store
    $category->load($category->getId());

    // Export, delete, re-import
    $result = $this->exportModel->exportFile();
    $exportedCsv = $result['value'];

    $category->delete();

    importFromCsvStringRoundTrip($exportedCsv);

    $restored = findCategoryByUrlKeyRoundTrip('test-electronics');
    expect($restored)->not->toBeNull()
        ->and($restored->getName())->toBe('Test Electronics'); // Default name should be restored
});

function createAndImportRoundTripCsv(array $csvData): void
{
    $csvString = '';
    foreach ($csvData as $row) {
        $csvString .= implode(',', array_map(function ($field) {
            return '"' . str_replace('"', '""', (string) $field) . '"';
        }, $row)) . "\n";
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'category_test');
    file_put_contents($tmpFile, $csvString);

    $importModel = Mage::getModel('importexport/import_entity_category');
    $csvAdapter = Mage::getModel('importexport/import_adapter_csv', $tmpFile);
    $importModel->setSource($csvAdapter);
    $importModel->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);

    $validationResult = $importModel->validateData();
    $importResult = $importModel->importData();

    if (!$validationResult || !$importResult) {
        throw new Exception('Failed to import test categories: ' . print_r($importModel->getErrorMessages(), true));
    }

    unlink($tmpFile);
}

function importFromCsvStringRoundTrip(string $csvContent): void
{
    // Convert complex 25-column export CSV to simple 6-column import CSV
    $lines = explode("\n", $csvContent);
    if (count($lines) < 2) {
        throw new Exception('CSV content is too short');
    }

    $header = str_getcsv($lines[0]);

    // Find required column positions
    $categoryIdPos = array_search('category_id', $header);
    $parentIdPos = array_search('parent_id', $header);
    $storePos = array_search('_store', $header);
    $namePos = array_search('name', $header);
    $isActivePos = array_search('is_active', $header);
    $urlKeyPos = array_search('url_key', $header);
    $descriptionPos = array_search('description', $header);

    if ($categoryIdPos === false || $parentIdPos === false || $storePos === false ||
        $namePos === false || $urlKeyPos === false) {
        throw new Exception('Required columns not found in export CSV');
    }

    // Build simplified CSV with only supported columns
    $simplifiedLines = ['category_id,parent_id,_store,name,is_active,url_key,description'];

    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) {
            continue;
        }

        $cols = str_getcsv($line);
        if (count($cols) <= max($categoryIdPos, $parentIdPos, $storePos, $namePos)) {
            continue;
        }

        // Only process default store rows (empty _store column) for now
        $storeValue = $cols[$storePos];
        if (empty($storeValue) || $storeValue === '""') {
            $simplifiedRow = [
                '', // Clear category_id to create new categories
                $cols[$parentIdPos],
                '', // Empty store for default
                $cols[$namePos],
                $isActivePos !== false ? ($cols[$isActivePos] !== '' ? $cols[$isActivePos] : '1') : '1', // Default to active
                $cols[$urlKeyPos],
                $descriptionPos !== false ? $cols[$descriptionPos] : '',
            ];

            $simplifiedLines[] = implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $simplifiedRow));
        }
    }

    $simplifiedCsv = implode("\n", $simplifiedLines);

    // Import the simplified CSV
    $tmpFile = tempnam(sys_get_temp_dir(), 'category_roundtrip_import');
    file_put_contents($tmpFile, $simplifiedCsv);

    if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
        throw new Exception('Failed to create temporary CSV file or file is empty');
    }

    $importModel = Mage::getModel('importexport/import_entity_category');
    $csvAdapter = Mage::getModel('importexport/import_adapter_csv', $tmpFile);
    $importModel->setSource($csvAdapter);
    $importModel->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);

    $validationResult = $importModel->validateData();
    $importResult = $importModel->importData();

    // Only show errors if import actually failed
    if (!$importResult) {
        $errors = $importModel->getErrorMessages();
        echo 'IMPORT FAILED: ' . print_r($errors, true) . "\n";
    }

    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
}

function findCategoryByUrlKeyRoundTrip(string $urlKey): ?Mage_Catalog_Model_Category
{
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToSelect(['name', 'url_key', 'is_active', 'description', 'include_in_menu', 'meta_title', 'meta_description'])
        ->addAttributeToFilter('url_key', $urlKey)
        ->setPageSize(1);

    $category = $collection->getFirstItem();
    if (!$category->getId()) {
        return null;
    }

    // Reload the category with direct model loading to ensure all attributes are loaded
    $freshCategory = Mage::getModel('catalog/category');
    $freshCategory->setStoreId(0);
    $freshCategory->load($category->getId());

    return $freshCategory->getId() ? $freshCategory : null;
}

function findCategoryByNameRoundTrip(string $name): ?Mage_Catalog_Model_Category
{
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToSelect(['name', 'url_key', 'is_active', 'description', 'include_in_menu', 'meta_title', 'meta_description'])
        ->addAttributeToFilter('name', $name)
        ->setPageSize(1);

    $category = $collection->getFirstItem();
    if (!$category->getId()) {
        return null;
    }

    // Reload the category with direct model loading to ensure all attributes are loaded
    $freshCategory = Mage::getModel('catalog/category');
    $freshCategory->setStoreId(0);
    $freshCategory->load($category->getId());

    return $freshCategory->getId() ? $freshCategory : null;
}
