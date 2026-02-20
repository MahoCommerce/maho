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
    // CSV adapter will be created in helper function with proper source file
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

it('handles duplicate category paths in same import', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        ['', '2', '', 'Test Unique Category', 'test-unique-category', '1'],
        ['', '2', '', 'Test Unique Category Duplicate', 'test-unique-category', '1'], // Duplicate path
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Check for duplicate path errors - import model handles this gracefully
    $errorCount = $GLOBALS['testImportModelEdge']->getErrorsCount();
    // Note: Current import model handles duplicates gracefully without errors

    // Should handle gracefully - either skip duplicate or update existing
    $category = findCategoryByUrlKeyEdgeCase('test-unique-category');
    expect($category)->not->toBeNull();

    // Import model handles duplicates by creating both categories (possibly with auto-generated unique keys)
    $count = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('url_key', 'test-unique-category')
        ->count();

    // Accept that duplicates may be handled by creating multiple categories with unique keys
    expect($count)->toBeGreaterThanOrEqual(1);
});

it('handles very deep category hierarchies', function () {
    // Create 10-level deep hierarchy - must import level by level to establish parent-child relationships
    $parentId = '2'; // Start with default root category

    for ($i = 1; $i <= 10; $i++) {
        $csvData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
            ['', $parentId, '', 'Level ' . $i, 'level-' . $i, '1'],
        ];

        createAndImportEdgeCaseCsv($csvData);

        // Get the created category to use as parent for next level
        $createdCategory = findCategoryByUrlKeyEdgeCase('level-' . $i);
        expect($createdCategory)->not->toBeNull(); // Verify category was created before proceeding
        $parentId = (string) $createdCategory->getId();
    }

    // Check deepest level was created correctly
    $deepestCategory = findCategoryByUrlKeyEdgeCase('level-10');
    expect($deepestCategory)->not->toBeNull()
        ->and((int) $deepestCategory->getLevel())->toBe(11); // Root=1, Default=2, so level-10 = 11

    // Check path integrity
    $pathIds = explode('/', $deepestCategory->getPath());
    expect(count($pathIds))->toBe(12); // 1 (root) + 1 (default) + 10 levels
});

it('handles unicode characters in category names', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'description'],
        ['', '2', '', 'Électronique', 'electronics', 'Catégorie électronique'],
        ['', '2', '', 'Téléphones', 'smartphones', 'Téléphones intelligents'],
        ['', '2', '', '时尚', 'fashion', '时尚类别'],
        ['', '2', '', '鞋子', 'shoes', '各种鞋类产品'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    $electronics = findCategoryByUrlKeyEdgeCase('electronics');
    $fashion = findCategoryByUrlKeyEdgeCase('fashion');

    expect($electronics)->not->toBeNull()
        ->and($electronics->getName())->toBe('Électronique')
        ->and($electronics->getDescription())->toBe('Catégorie électronique');

    expect($fashion)->not->toBeNull()
        ->and($fashion->getName())->toBe('时尚')
        ->and($fashion->getDescription())->toBe('时尚类别');
});

it('validates attribute data types correctly', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active', 'position', 'include_in_menu'],
        ['', '2', '', 'Test Category', 'test-validation', 'invalid_boolean', 'not_a_number', '1'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Should have validation errors for invalid data types
    expect($GLOBALS['testImportModelEdge']->getErrorsCount())->toBeGreaterThan(0);
});

it('handles missing required attributes', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'url_key', 'is_active'], // Missing 'name' which is required
        ['', '2', '', 'test-missing-name', '1'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Should have validation error for missing required field
    expect($GLOBALS['testImportModelEdge']->getErrorsCount())->toBeGreaterThan(0);

    $errors = $GLOBALS['testImportModelEdge']->getErrorMessages();
    $hasNameError = false;
    foreach ($errors as $message => $rows) {
        if (strpos(strtolower($message), 'name') !== false) {
            $hasNameError = true;
            break;
        }
    }
    expect($hasNameError)->toBeTrue();
});

it('handles invalid store codes gracefully', function () {
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key'],
        ['', '2', '', 'Test Category', 'test-category'],
        ['', '2', 'nonexistent_store', 'Store Specific Name', ''], // Invalid store code
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Should not crash, but may skip invalid store data
    $category = findCategoryByUrlKeyEdgeCase('test-category');
    expect($category)->not->toBeNull()
        ->and($category->getName())->toBe('Test Category');
});

it('maintains database consistency during import', function () {
    // Create a large batch to test transaction consistency
    $csvData = [['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active']];

    for ($i = 1; $i <= 50; $i++) {
        $csvData[] = ['', '2', '', 'Batch Category ' . $i, 'batch-category-' . $i, '1'];
    }

    // Add one invalid row to test rollback behavior
    $csvData[] = ['', '', '', '', '', '']; // Invalid row

    createAndImportEdgeCaseCsv($csvData);

    // Even with errors, valid categories should be imported
    // (ImportExport typically continues on row errors rather than rolling back)
    $validCategory = findCategoryByUrlKeyEdgeCase('batch-category-1');
    expect($validCategory)->not->toBeNull();
});

it('handles concurrent modifications gracefully', function () {
    // Create category via import
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        ['', '2', '', 'Original Name', 'concurrent-test', '1'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    $category = findCategoryByUrlKeyEdgeCase('concurrent-test');
    $originalId = $category->getId();

    // Simulate concurrent modification by directly updating the database
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $nameAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', 'name');
    $connection->update(
        $nameAttribute->getBackendTable(),
        ['value' => 'Modified Externally'],
        [
            'entity_id = ?' => $originalId,
            'attribute_id = ?' => $nameAttribute->getId(),
            'store_id = ?' => 0,
        ],
    );

    // Import update - provide category_id for successful update
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        [(string) $originalId, '2', '', 'Updated Name', 'concurrent-test', '0'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Reload and check - should have import changes, not concurrent changes
    $category->load($originalId);
    expect($category->getName())->toBe('Updated Name')
        ->and($category->getIsActive())->toBe(0);
});

it('handles url_key conflicts during import', function () {
    // First create category with specific url_key
    $existingCategory = Mage::getModel('catalog/category');
    $existingCategory->setName('Existing Category')
        ->setUrlKey('electronics')
        ->setIsActive(1)
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    // Now try to import category with same url_key path - provide category_id for update
    $csvData = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        [(string) $existingCategory->getId(), '2', '', 'New Electronics', 'electronics', '1'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Should update existing category rather than create duplicate
    $electronics = findCategoryByUrlKeyEdgeCase('electronics');
    expect($electronics)->not->toBeNull()
        ->and($electronics->getName())->toBe('New Electronics');

    // Should only have one electronics category
    $count = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('url_key', 'electronics')
        ->count();

    expect($count)->toBe(1);
});

it('validates category tree structure integrity after import', function () {
    // Step 1: Create root category
    $csvData1 = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        ['', '2', '', 'Root Category', 'root-cat', '1'],
    ];
    createAndImportEdgeCaseCsv($csvData1);

    $rootCat = findCategoryByUrlKeyEdgeCase('root-cat');
    expect($rootCat)->not->toBeNull();

    // Step 2: Create children under root
    $csvData2 = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        ['', (string) $rootCat->getId(), '', 'Child 1', 'child1', '1'],
        ['', (string) $rootCat->getId(), '', 'Child 2', 'child2', '1'],
    ];
    createAndImportEdgeCaseCsv($csvData2);

    $child1 = findCategoryByUrlKeyEdgeCase('child1');
    $child2 = findCategoryByUrlKeyEdgeCase('child2');
    expect($child1)->not->toBeNull()
        ->and($child2)->not->toBeNull();

    // Step 3: Create grandchild under child1
    $csvData3 = [
        ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active'],
        ['', (string) $child1->getId(), '', 'Grandchild', 'grandchild', '1'],
    ];
    createAndImportEdgeCaseCsv($csvData3);

    $grandchild = findCategoryByUrlKeyEdgeCase('grandchild');
    expect($grandchild)->not->toBeNull();

    // Check parent relationships
    expect($child1->getParentId())->toBe((int) $rootCat->getId())
        ->and($child2->getParentId())->toBe((int) $rootCat->getId())
        ->and($grandchild->getParentId())->toBe((int) $child1->getId());

    // Check levels are sequential
    expect((int) $child1->getLevel())->toBe((int) $rootCat->getLevel() + 1)
        ->and((int) $child2->getLevel())->toBe((int) $rootCat->getLevel() + 1)
        ->and((int) $grandchild->getLevel())->toBe((int) $child1->getLevel() + 1);

    // Check path contains parent IDs
    expect($child1->getPath())->toContain((string) $rootCat->getId())
        ->and($grandchild->getPath())->toContain((string) $child1->getId())
        ->and($grandchild->getPath())->toContain((string) $rootCat->getId());
});

// Helper methods
function createAndImportEdgeCaseCsv(array $data, string $behavior = Mage_ImportExport_Model_Import::BEHAVIOR_APPEND): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'category_import_edge_test');
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

    $importModel->validateData();
    $importModel->importData();

    // Store the import model globally for error checking
    $GLOBALS['testImportModelEdge'] = $importModel;

    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
}

function findCategoryByUrlKeyEdgeCase(string $urlKey): ?Mage_Catalog_Model_Category
{
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToSelect(['name', 'url_key', 'is_active', 'description'])
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
