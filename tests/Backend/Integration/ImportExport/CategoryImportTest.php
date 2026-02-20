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
    // Create import model
    $this->importModel = Mage::getModel('importexport/import_entity_category');

    // CSV adapter will be created in helper function with proper source file

    // Store original category count
    $this->originalCategoryCount = Mage::getModel('catalog/category')
        ->getCollection()
        ->addAttributeToFilter('level', ['gt' => 0])
        ->count();
});

afterEach(function () {
    // Clean up any created categories
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('level', ['gt' => 0])
        ->addAttributeToFilter('entity_id', ['gt' => 2]); // Don't delete default category

    foreach ($collection as $category) {
        try {
            $category->delete();
        } catch (Exception $e) {
            // Ignore deletion errors in cleanup
        }
    }
});

it('creates new categories with parent_id', function () {
    // First import top-level categories
    $csvData1 = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', '2', '', 'Electronics', '1', 'electronics'],
        ['', '2', '', 'Clothing', '1', 'clothing'],
    ];
    createAndImportCsv($csvData1);

    $electronics = findCategoryByUrlKey('electronics');
    $clothing = findCategoryByUrlKey('clothing');

    // Then import subcategories
    $csvData2 = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', (string) $electronics->getId(), '', 'Phones', '1', 'phones'],
    ];
    createAndImportCsv($csvData2);

    // Check electronics category was created
    expect($electronics)->not->toBeNull()
        ->and($electronics->getName())->toBe('Electronics')
        ->and($electronics->getIsActive())->toBe(1);

    // Check phones subcategory was created with correct parent
    $phones = findCategoryByUrlKey('phones');
    expect($phones)->not->toBeNull()
        ->and($phones->getName())->toBe('Phones')
        ->and($phones->getParentId())->toBe((int) $electronics->getId());

    // Check clothing category was created
    $clothing = findCategoryByUrlKey('clothing');
    expect($clothing)->not->toBeNull()
        ->and($clothing->getName())->toBe('Clothing');
});

it('updates existing categories', function () {
    // First create a category
    $category = Mage::getModel('catalog/category');
    $category->setName('Original Name')
        ->setUrlKey('test-category')
        ->setIsActive(0)
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active'],
        [(string) $category->getId(), '', '', 'Updated Name', '1'],
    ];

    createAndImportCsv($csvData);

    // Reload category and check it was updated
    $category->load($category->getId());
    expect($category->getName())->toBe('Updated Name')
        ->and($category->getIsActive())->toBe(1);
});

it('handles multi-store data correctly', function () {
    // First create the category
    $csvData1 = [
        ['category_id', 'parent_id', '_store', 'name', 'description', 'url_key'],
        ['', '2', '', 'Test English', 'English description', 'test-multistore'],
    ];
    createAndImportCsv($csvData1);

    $category = findCategoryByUrlKey('test-multistore');

    // Then update with store-specific data
    $csvData2 = [
        ['category_id', 'parent_id', '_store', 'name', 'description'],
        [(string) $category->getId(), '', 'default', 'Test German', 'German description'],
    ];
    createAndImportCsv($csvData2);
    expect($category)->not->toBeNull();

    // Check default store data (admin store)
    $adminCategory = Mage::getModel('catalog/category');
    $adminCategory->setStoreId(0);
    $adminCategory->load($category->getId());
    expect($adminCategory->getName())->toBe('Test English')
        ->and($adminCategory->getDescription())->toBe('English description');

    // Check store-specific data (store 1)
    $storeCategory = Mage::getModel('catalog/category');
    $storeCategory->setStoreId(1);
    $storeCategory->load($category->getId());
    expect($storeCategory->getName())->toBe('Test German')
        ->and($storeCategory->getDescription())->toBe('German description');
});

it('validates required parent_id field for new categories', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name'],
        ['', '', '', 'Invalid Category'], // No parent_id for new category
    ];

    createAndImportCsv($csvData);

    // Should have validation errors
    expect($GLOBALS['testImportModel']->getErrorsCount())->toBeGreaterThan(0);

    $errors = $GLOBALS['testImportModel']->getErrorMessages();
    $hasPathEmptyError = false;
    foreach ($errors as $message => $rows) {
        if (strpos($message, 'Category path is empty') !== false) {
            $hasPathEmptyError = true;
            break;
        }
    }
    expect($hasPathEmptyError)->toBeTrue();
});

it('validates parent_id format', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name'],
        ['', 'invalid_id', '', 'Invalid Category'],
        ['', '99999', '', 'Non-existent Parent'], // Non-existent parent ID
    ];

    createAndImportCsv($csvData);

    // Should have validation errors for invalid parent IDs
    expect($GLOBALS['testImportModel']->getErrorsCount())->toBeGreaterThan(0);
});

it('validates parent category exists', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name'],
        ['', '9999', '', 'Child Category'], // Non-existent parent ID
    ];

    createAndImportCsv($csvData);

    // Should have validation errors for missing parent
    expect($GLOBALS['testImportModel']->getErrorsCount())->toBeGreaterThan(0);

    $errors = $GLOBALS['testImportModel']->getErrorMessages();
    $hasParentError = false;
    foreach ($errors as $message => $rows) {
        if (strpos($message, 'not found') !== false) {
            $hasParentError = true;
            break;
        }
    }
    expect($hasParentError)->toBeTrue();
});

it('handles delete behavior correctly', function () {
    // Create test category first
    $category = Mage::getModel('catalog/category');
    $category->setName('To Delete')
        ->setUrlKey('to-delete')
        ->setIsActive(1)
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    $categoryId = $category->getId();

    $csvData = [
        ['category_id', 'parent_id', '_store', 'name'],
        [(string) $categoryId, '', '', 'To Delete'],
    ];

    createAndImportCsv($csvData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

    // Category should be deleted
    $deletedCategory = Mage::getModel('catalog/category')->load($categoryId);
    expect($deletedCategory->getId())->toBeNull();
});

it('maintains category tree integrity', function () {
    // Import Level 1 first
    $csvData1 = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', '2', '', 'Level 1', '1', 'level1'],
    ];
    createAndImportCsv($csvData1);
    $level1 = findCategoryByUrlKey('level1');

    // Import Level 2 under Level 1
    $csvData2 = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', (string) $level1->getId(), '', 'Level 2', '1', 'level2'],
    ];
    createAndImportCsv($csvData2);
    $level2 = findCategoryByUrlKey('level2');

    // Import Level 3 under Level 2
    $csvData3 = [
        ['category_id', 'parent_id', '_store', 'name', 'is_active', 'url_key'],
        ['', (string) $level2->getId(), '', 'Level 3', '1', 'level3'],
    ];
    createAndImportCsv($csvData3);
    $level3 = findCategoryByUrlKey('level3');

    expect($level1)->not->toBeNull()
        ->and($level2)->not->toBeNull()
        ->and($level3)->not->toBeNull();

    // Check parent-child relationships
    expect($level2->getParentId())->toBe((int) $level1->getId())
        ->and($level3->getParentId())->toBe((int) $level2->getId());

    // Check levels are correct
    expect((int) $level1->getLevel())->toBe(2) // Root is level 1, so first level is 2
        ->and((int) $level2->getLevel())->toBe(3)
        ->and((int) $level3->getLevel())->toBe(4);

    // Check paths are correct
    expect($level1->getPath())->toContain('1/2/' . $level1->getId()) // Under default category
        ->and($level2->getPath())->toContain($level1->getId() . '/' . $level2->getId())
        ->and($level3->getPath())->toContain($level2->getId() . '/' . $level3->getId());
});

it('generates url_key from name when missing', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name'],
        ['', '2', '', 'Auto Generated Key!'], // Name with special chars, no url_key
    ];

    createAndImportCsv($csvData);

    $category = findCategoryByUrlKey('auto-generated-key-');
    expect($category)->not->toBeNull()
        ->and($category->getName())->toBe('Auto Generated Key!')
        ->and($category->getUrlKey())->toBe('auto-generated-key-');
});

it('handles scope resolution correctly', function () {
    // First create categories in default scope
    $csvData1 = [
        ['category_id', 'parent_id', '_store', 'name', 'description', 'url_key'],
        ['', '2', '', 'Test Category', 'Default description', 'test-scope'],
        ['', '2', '', 'Another Category', 'Another description', 'another-category'],
    ];
    createAndImportCsv($csvData1);

    $category = findCategoryByUrlKey('test-scope');

    // Then update with store scope data
    $csvData2 = [
        ['category_id', 'parent_id', '_store', 'name', 'description'],
        [(string) $category->getId(), '', 'default', '', 'Store description'], // Store scope for same category
    ];
    createAndImportCsv($csvData2);

    $category = findCategoryByUrlKey('test-scope');
    expect($category)->not->toBeNull();

    // Default store values
    $adminCategory = Mage::getModel('catalog/category');
    $adminCategory->setStoreId(0);
    $adminCategory->load($category->getId());
    expect($adminCategory->getName())->toBe('Test Category')
        ->and($adminCategory->getDescription())->toBe('Default description');

    // Store-specific values
    $storeCategory = Mage::getModel('catalog/category');
    $storeCategory->setStoreId(1);
    $storeCategory->load($category->getId());
    expect($storeCategory->getDescription())->toBe('Store description');
});

// Helper methods
function createAndImportCsv(array $data, string $behavior = Mage_ImportExport_Model_Import::BEHAVIOR_APPEND): void
{
    // Create temporary CSV file
    $tmpFile = tempnam(sys_get_temp_dir(), 'category_import_test');
    $handle = fopen($tmpFile, 'w');

    foreach ($data as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    // Ensure file exists before creating adapter
    if (!file_exists($tmpFile)) {
        throw new Exception('Failed to create temporary CSV file');
    }

    // Create fresh import model and CSV adapter
    $importModel = Mage::getModel('importexport/import_entity_category');
    $csvAdapter = Mage::getModel('importexport/import_adapter_csv', $tmpFile);
    $importModel->setSource($csvAdapter);
    $importModel->setParameters(['behavior' => $behavior]);

    // Validate and import
    $importModel->validateData();
    $importModel->importData();

    // Store the import model globally for error checking
    $GLOBALS['testImportModel'] = $importModel;

    // Clean up
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
}

function findCategoryByUrlKey(string $urlKey): ?Mage_Catalog_Model_Category
{
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToSelect('*')
        ->addAttributeToFilter('url_key', $urlKey)
        ->setPageSize(1);

    $category = $collection->getFirstItem();
    if (!$category->getId()) {
        return null;
    }

    // Return fresh model instance that can be reloaded for different stores
    $freshCategory = Mage::getModel('catalog/category');
    $freshCategory->setStoreId(0); // Ensure we start with admin store
    $freshCategory->load($category->getId());

    return $freshCategory->getId() ? $freshCategory : null;
}
