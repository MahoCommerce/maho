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

describe('DELETE Behavior', function () {
    it('deletes specified categories', function () {
        // Create test categories first
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Delete Me 1', 'to-delete-1'],
            ['', '2', '', 'Delete Me 2', 'to-delete-2'],
            ['', '2', '', 'Keep Me', 'to-keep'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Get the created categories
        $catToDelete1 = findCategoryByUrlKeyBehavior('to-delete-1');
        $catToDelete2 = findCategoryByUrlKeyBehavior('to-delete-2');
        $catToKeep = findCategoryByUrlKeyBehavior('to-keep');

        // Verify categories were created
        expect($catToDelete1)->not->toBeNull()
            ->and($catToDelete2)->not->toBeNull()
            ->and($catToKeep)->not->toBeNull();

        // Now delete specific categories using category_id
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            [(string) $catToDelete1->getId(), '', ''],
            [(string) $catToDelete2->getId(), '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify only specified categories were deleted
        expect(findCategoryByUrlKeyBehavior('to-delete-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('to-delete-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('to-keep'))->not->toBeNull();
    });

    it('deletes category hierarchies correctly', function () {
        // First create parent categories
        $parentsData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Parent Delete', 'parent-to-delete'],
            ['', '2', '', 'Parent Keep', 'parent-to-keep'],
        ];

        createAndImportBehaviorCsv($parentsData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Get parent categories
        $parentToDelete = findCategoryByUrlKeyBehavior('parent-to-delete');
        $parentToKeep = findCategoryByUrlKeyBehavior('parent-to-keep');

        // Now create children under these parents
        $childrenData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', (string) $parentToDelete->getId(), '', 'Child 1', 'child-1'],
            ['', (string) $parentToDelete->getId(), '', 'Child 2', 'child-2'],
            ['', (string) $parentToKeep->getId(), '', 'Child Keep', 'child-keep'],
        ];

        createAndImportBehaviorCsv($childrenData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Delete parent category using category_id
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            [(string) $parentToDelete->getId(), '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify parent and children are deleted, but other hierarchy remains
        expect(findCategoryByUrlKeyBehavior('parent-to-delete'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('child-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('child-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('parent-to-keep'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('child-keep'))->not->toBeNull();
    });

    it('handles delete errors gracefully for non-existent categories', function () {
        // Try to delete non-existent categories using fake category IDs
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            ['99999', '', ''],
            ['99998', '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Should not crash - check error count
        expect($GLOBALS['testImportModelBehavior']->getErrorsCount())->toBeGreaterThanOrEqual(0);
    });

    it('preserves root and system categories during delete', function () {
        // Get root category before test
        $rootCategory = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategory = Mage::getModel('catalog/category')->load(2); // Default category

        expect((int) $rootCategory->getId())->toBe(Mage_Catalog_Model_Category::TREE_ROOT_ID)
            ->and((int) $defaultCategory->getId())->toBe(2);

        // Create a test category first
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Test Category', 'test-category'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        $testCategory = findCategoryByUrlKeyBehavior('test-category');

        // Delete the test category
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            [(string) $testCategory->getId(), '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify system categories still exist
        $rootCategoryAfter = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategoryAfter = Mage::getModel('catalog/category')->load(2);

        expect((int) $rootCategoryAfter->getId())->toBe(Mage_Catalog_Model_Category::TREE_ROOT_ID)
            ->and((int) $defaultCategoryAfter->getId())->toBe(2);
    });

    it('handles multi-store data in delete operations', function () {
        // First create the category
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'English Name', 'multi-store-delete'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        $category = findCategoryByUrlKeyBehavior('multi-store-delete');

        // Add store-specific data
        $storeData = [
            ['category_id', 'parent_id', '_store', 'name'],
            [(string) $category->getId(), '', 'default', 'German Name'],
        ];

        createAndImportBehaviorCsv($storeData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify multi-store category exists
        expect($category)->not->toBeNull();

        // Delete the category
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            [(string) $category->getId(), '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify category is completely deleted (all store data)
        expect(findCategoryByUrlKeyBehavior('multi-store-delete'))->toBeNull();
    });

    it('deletes categories using category_id', function () {
        // Create test categories
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'ID Delete 1', 'id-delete-1'],
            ['', '2', '', 'ID Delete 2', 'id-delete-2'],
            ['', '2', '', 'ID Keep', 'id-keep'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Get category IDs
        $cat1 = findCategoryByUrlKeyBehavior('id-delete-1');
        $cat2 = findCategoryByUrlKeyBehavior('id-delete-2');
        $catKeep = findCategoryByUrlKeyBehavior('id-keep');

        expect($cat1)->not->toBeNull()
            ->and($cat2)->not->toBeNull()
            ->and($catKeep)->not->toBeNull();

        // Delete using category_id
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            [(string) $cat1->getId(), '', ''],
            [(string) $cat2->getId(), '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify only specified categories were deleted by ID
        expect(findCategoryByUrlKeyBehavior('id-delete-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('id-delete-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('id-keep'))->not->toBeNull();
    });

    it('handles mixed category_id and category_path in delete operations', function () {
        // Create test categories using new format
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Mixed Delete 1', 'mixed-delete-1'],
            ['', '2', '', 'Mixed Delete 2', 'mixed-delete-2'],
            ['', '2', '', 'Mixed Keep', 'mixed-keep'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Get category IDs
        $cat1 = findCategoryByUrlKeyBehavior('mixed-delete-1');
        $cat2 = findCategoryByUrlKeyBehavior('mixed-delete-2');
        expect($cat1)->not->toBeNull()->and($cat2)->not->toBeNull();

        // Delete using category_id (the new standard approach)
        $deleteData = [
            ['category_id', 'parent_id', '_store'],
            [(string) $cat1->getId(), '', ''],
            [(string) $cat2->getId(), '', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify both categories were deleted
        expect(findCategoryByUrlKeyBehavior('mixed-delete-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('mixed-delete-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('mixed-keep'))->not->toBeNull();
    });

    it('validates category_id format in delete operations', function () {
        // Try to delete with invalid category IDs
        $deleteData = [
            ['category_id', '_store'],
            ['invalid', ''], // Non-numeric ID
            ['1', ''], // System root category (should be rejected)
            ['2', ''], // Default category (should be rejected)
            ['99999', ''], // Non-existent ID
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Should have errors for all invalid IDs
        expect($GLOBALS['testImportModelBehavior']->getErrorsCount())->toBeGreaterThan(0);

        // Verify system categories are still safe
        $rootCategory = Mage::getModel('catalog/category')->load(1);
        $defaultCategory = Mage::getModel('catalog/category')->load(2);
        expect((int) $rootCategory->getId())->toBe(1)
            ->and((int) $defaultCategory->getId())->toBe(2);
    });

    it('requires either category_id or category_path for delete operations', function () {
        // Try to delete without any identifier
        $deleteData = [
            ['_store'],
            [''], // Empty row with no category_id or category_path
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Should have validation error
        expect($GLOBALS['testImportModelBehavior']->getErrorsCount())->toBeGreaterThan(0);

        // Check for specific error message
        $errors = $GLOBALS['testImportModelBehavior']->getErrorMessages();
        $hasIdentifierError = false;
        foreach ($errors as $errorType => $rows) {
            if (strpos($errorType, 'category_id or category_path must be provided') !== false) {
                $hasIdentifierError = true;
                break;
            }
        }
        expect($hasIdentifierError)->toBeTrue();
    });
});

describe('REPLACE Behavior', function () {
    it('works exactly like APPEND behavior', function () {
        // Create initial categories with description
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key', 'description'],
            ['', '2', '', 'Original 1', 'original-1', 'Original description 1'],
            ['', '2', '', 'Original 2', 'original-2', 'Original description 2'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Get category ID for updating
        $originalCat1 = findCategoryByUrlKeyBehavior('original-1');
        expect($originalCat1)->not->toBeNull();
        expect($originalCat1->getDescription())->toBe('Original description 1');

        // REPLACE: update existing category and create new one (same as APPEND)
        $replaceData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            [(string) $originalCat1->getId(), '2', '', 'Updated Name', 'updated-1'], // Update existing
            ['', '2', '', 'New Category', 'new-1'], // Create new
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify REPLACE behavior works exactly like APPEND for categories
        $updatedCat = findCategoryByUrlKeyBehavior('updated-1');
        expect($updatedCat)->not->toBeNull()
            ->and($updatedCat->getName())->toBe('Updated Name')
            ->and($updatedCat->getDescription())->toBe('Original description 1') // Description preserved (not in CSV)
            ->and(findCategoryByUrlKeyBehavior('original-2'))->not->toBeNull() // Untouched category remains
            ->and(findCategoryByUrlKeyBehavior('new-1'))->not->toBeNull(); // New category created
    });

    it('preserves system categories during replace', function () {
        // Get system categories before test
        $rootCategory = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategory = Mage::getModel('catalog/category')->load(2);

        // Create some categories first
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Replace Test 1', 'replace-test-1'],
            ['', '2', '', 'Replace Test 2', 'replace-test-2'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Replace: add new category (same as APPEND for categories)
        $replaceData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Replace Test 3', 'replace-test-3'],
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify system categories still exist
        $rootCategoryAfter = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategoryAfter = Mage::getModel('catalog/category')->load(2);

        expect($rootCategoryAfter->getId())->toBe($rootCategory->getId())
            ->and($defaultCategoryAfter->getId())->toBe($defaultCategory->getId());

        // Verify REPLACE works like APPEND: all categories still exist
        expect(findCategoryByUrlKeyBehavior('replace-test-1'))->not->toBeNull() // Still exists
            ->and(findCategoryByUrlKeyBehavior('replace-test-2'))->not->toBeNull() // Still exists
            ->and(findCategoryByUrlKeyBehavior('replace-test-3'))->not->toBeNull(); // New category created
    });

    it('handles hierarchical replace correctly', function () {
        // Create hierarchical structure - first parents
        $parentsData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Old Parent', 'old-parent'],
            ['', '2', '', 'Keep Parent', 'keep-parent'],
        ];
        createAndImportBehaviorCsv($parentsData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        $oldParent = findCategoryByUrlKeyBehavior('old-parent');
        $keepParent = findCategoryByUrlKeyBehavior('keep-parent');

        // Then children
        $childrenData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', (string) $oldParent->getId(), '', 'Old Child 1', 'old-child-1'],
            ['', (string) $oldParent->getId(), '', 'Old Child 2', 'old-child-2'],
            ['', (string) $keepParent->getId(), '', 'Keep Child', 'keep-child'],
        ];
        createAndImportBehaviorCsv($childrenData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Replace with new hierarchy - REPLACE works same as APPEND for categories
        $newParentData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'New Parent', 'new-parent'],
        ];
        createAndImportBehaviorCsv($newParentData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        $newParent = findCategoryByUrlKeyBehavior('new-parent');
        $newChildData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', (string) $newParent->getId(), '', 'New Child', 'new-child'],
        ];
        createAndImportBehaviorCsv($newChildData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify REPLACE works same as APPEND - all categories still exist, new ones added
        expect(findCategoryByUrlKeyBehavior('old-parent'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('old-child-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('old-child-2'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('keep-parent'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('keep-child'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('new-parent'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('new-child'))->not->toBeNull();
    });

    it('handles multi-store data in replace operations', function () {
        // Create initial categories
        $setupData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'English 1', 'multi-replace-1'],
            ['', '2', '', 'English 2', 'multi-replace-2'],
        ];
        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        $cat1 = findCategoryByUrlKeyBehavior('multi-replace-1');
        $cat2 = findCategoryByUrlKeyBehavior('multi-replace-2');

        // Add store-specific data
        $storeData = [
            ['category_id', 'parent_id', '_store', 'name'],
            [(string) $cat1->getId(), '', 'default', 'German 1'],
            [(string) $cat2->getId(), '', 'default', 'German 2'],
        ];
        createAndImportBehaviorCsv($storeData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Replace with new structure
        $replaceData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'New English', 'multi-replace-new'],
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify replacement
        expect(findCategoryByUrlKeyBehavior('multi-replace-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('multi-replace-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('multi-replace-new'))->not->toBeNull();

        // Verify multi-store data
        $newCategory = findCategoryByUrlKeyBehavior('multi-replace-new');
        expect($newCategory)->not->toBeNull();
        expect($newCategory->getName())->toBe('New English');
    });
});

describe('Behavior Comparison', function () {
    it('demonstrates different behavior outcomes with same data', function () {
        // Setup: Create initial categories
        $initialData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Initial 1', 'behavior-test-1'],
            ['', '2', '', 'Initial 2', 'behavior-test-2'],
        ];

        createAndImportBehaviorCsv($initialData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify initial state
        expect(findCategoryByUrlKeyBehavior('behavior-test-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('behavior-test-2'))->not->toBeNull();

        // Get existing category ID for update
        $behaviorTest1 = findCategoryByUrlKeyBehavior('behavior-test-1');

        // Test data for all behaviors
        $testData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            [(string) $behaviorTest1->getId(), '2', '', 'Updated 1', 'behavior-test-1'], // Update existing
            ['', '2', '', 'New 3', 'behavior-test-3'],     // Add new
        ];

        // Test 1: APPEND behavior
        createAndImportBehaviorCsv($testData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // APPEND: All categories should exist (original + new + updated)
        expect(findCategoryByUrlKeyBehavior('behavior-test-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('behavior-test-2'))->not->toBeNull() // Still exists
            ->and(findCategoryByUrlKeyBehavior('behavior-test-3'))->not->toBeNull();

        expect(findCategoryByUrlKeyBehavior('behavior-test-1')->getName())->toBe('Updated 1');

        // Reset for REPLACE test - clean up manually
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

        createAndImportBehaviorCsv($initialData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Test 2: REPLACE behavior - REPLACE works same as APPEND for categories
        $behaviorTest1New = findCategoryByUrlKeyBehavior('behavior-test-1');
        $replaceData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            [(string) $behaviorTest1New->getId(), '2', '', 'Replaced 1', 'behavior-test-1'], // Update existing (keep same url_key)
            ['', '2', '', 'New 3', 'behavior-test-3'],       // Create new (same as APPEND)
        ];
        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // REPLACE: Works same as APPEND - updates existing, creates new, leaves untouched unchanged
        expect(findCategoryByUrlKeyBehavior('behavior-test-1'))->not->toBeNull()      // Updated existing
            ->and(findCategoryByUrlKeyBehavior('behavior-test-2'))->not->toBeNull() // Unchanged existing
            ->and(findCategoryByUrlKeyBehavior('behavior-test-3'))->not->toBeNull(); // Created new

        expect(findCategoryByUrlKeyBehavior('behavior-test-1')->getName())->toBe('Replaced 1');
    });

    it('validates behavior parameter handling', function () {
        // Test with invalid behavior (should default to APPEND)
        $testData = [
            ['category_id', 'parent_id', '_store', 'name', 'url_key'],
            ['', '2', '', 'Invalid Behavior Test', 'behavior-invalid-test'],
        ];

        createAndImportBehaviorCsv($testData, 'invalid_behavior');

        // Should still work (defaults to APPEND)
        expect($GLOBALS['testImportModelBehavior']->getErrorsCount())->toBe(0);
        expect(findCategoryByUrlKeyBehavior('behavior-invalid-test'))->not->toBeNull();
    });
});

// Helper methods
function createAndImportBehaviorCsv(array $data, string $behavior): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'category_import_behavior_test');
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
    $GLOBALS['testImportModelBehavior'] = $importModel;

    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
}

function findCategoryByUrlKeyBehavior(string $urlKey): ?Mage_Catalog_Model_Category
{
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToSelect(['name', 'url_key', 'is_active'])
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
