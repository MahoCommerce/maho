<?php

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
        ['category_path', '_store', 'name', 'is_active'],
        ['test-unique-category', '', 'Test Unique Category', '1'],
        ['test-unique-category', '', 'Test Unique Category Duplicate', '1'], // Duplicate path
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Check for duplicate path errors
    $errorCount = $GLOBALS['testImportModelEdge']->getErrorsCount();
    expect($errorCount)->toBeGreaterThan(0); // Should have duplicate error

    // Should handle gracefully - either skip duplicate or update existing
    $category = findCategoryByUrlKeyEdgeCase('test-unique-category');
    expect($category)->not->toBeNull();

    // Only one category should exist with this unique url_key
    $count = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('url_key', 'test-unique-category')
        ->count();

    expect($count)->toBe(1);
});

it('handles very deep category hierarchies', function () {
    // Create 10-level deep hierarchy
    $csvData = [['category_path', '_store', 'name', 'is_active']];

    $path = '';
    for ($i = 1; $i <= 10; $i++) {
        $segment = 'level-' . $i;
        $path = $path ? $path . '/' . $segment : $segment;
        $csvData[] = [$path, '', 'Level ' . $i, '1'];
    }

    createAndImportEdgeCaseCsv($csvData);

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
        ['category_path', '_store', 'name', 'description'],
        ['electronics', '', 'Électronique', 'Catégorie électronique'],
        ['electronics/smartphones', '', 'Téléphones', 'Téléphones intelligents'],
        ['fashion', '', '时尚', '时尚类别'],
        ['fashion/shoes', '', '鞋子', '各种鞋类产品'],
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
        ['category_path', '_store', 'name', 'is_active', 'position', 'include_in_menu'],
        ['test-validation', '', 'Test Category', 'invalid_boolean', 'not_a_number', '1'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Should have validation errors for invalid data types
    expect($GLOBALS['testImportModelEdge']->getErrorsCount())->toBeGreaterThan(0);
});

it('handles missing required attributes', function () {
    $csvData = [
        ['category_path', '_store', 'is_active'], // Missing 'name' which is required
        ['test-missing-name', '', '1'],
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
        ['category_path', '_store', 'name'],
        ['test-category', '', 'Test Category'],
        ['', 'nonexistent_store', 'Store Specific Name'], // Invalid store code
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Should not crash, but may skip invalid store data
    $category = findCategoryByUrlKeyEdgeCase('test-category');
    expect($category)->not->toBeNull()
        ->and($category->getName())->toBe('Test Category');
});

it('maintains database consistency during import', function () {
    // Create a large batch to test transaction consistency
    $csvData = [['category_path', '_store', 'name', 'is_active']];

    for ($i = 1; $i <= 50; $i++) {
        $csvData[] = ['batch-category-' . $i, '', 'Batch Category ' . $i, '1'];
    }

    // Add one invalid row to test rollback behavior
    $csvData[] = ['', '', '', '']; // Invalid row

    createAndImportEdgeCaseCsv($csvData);

    // Even with errors, valid categories should be imported
    // (ImportExport typically continues on row errors rather than rolling back)
    $validCategory = findCategoryByUrlKeyEdgeCase('batch-category-1');
    expect($validCategory)->not->toBeNull();
});

it('handles concurrent modifications gracefully', function () {
    // Create category via import
    $csvData = [
        ['category_path', '_store', 'name', 'is_active'],
        ['concurrent-test', '', 'Original Name', '1'],
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

    // Import update
    $csvData = [
        ['category_path', '_store', 'name', 'is_active'],
        ['concurrent-test', '', 'Updated Name', '0'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    // Reload and check - should have import changes, not concurrent changes
    $category->load($originalId);
    expect($category->getName())->toBe('Updated Name')
        ->and($category->getIsActive())->toBe('0');
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

    // Now try to import category with same url_key path
    $csvData = [
        ['category_path', '_store', 'name', 'is_active'],
        ['electronics', '', 'New Electronics', '1'],
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
    $csvData = [
        ['category_path', '_store', 'name', 'is_active'],
        ['root-cat', '', 'Root Category', '1'],
        ['root-cat/child1', '', 'Child 1', '1'],
        ['root-cat/child2', '', 'Child 2', '1'],
        ['root-cat/child1/grandchild', '', 'Grandchild', '1'],
    ];

    createAndImportEdgeCaseCsv($csvData);

    $rootCat = findCategoryByUrlKeyEdgeCase('root-cat');
    $child1 = findCategoryByUrlKeyEdgeCase('child1');
    $child2 = findCategoryByUrlKeyEdgeCase('child2');
    $grandchild = findCategoryByUrlKeyEdgeCase('grandchild');

    // Validate tree structure
    expect($rootCat)->not->toBeNull()
        ->and($child1)->not->toBeNull()
        ->and($child2)->not->toBeNull()
        ->and($grandchild)->not->toBeNull();

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
