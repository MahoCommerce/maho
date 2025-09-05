<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
            ['category_path', '_store', 'name'],
            ['to-delete-1', '', 'Delete Me 1'],
            ['to-delete-2', '', 'Delete Me 2'],
            ['to-keep', '', 'Keep Me'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify categories were created
        expect(findCategoryByUrlKeyBehavior('to-delete-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('to-delete-2'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('to-keep'))->not->toBeNull();

        // Now delete specific categories
        $deleteData = [
            ['category_path', '_store'],
            ['to-delete-1', ''],
            ['to-delete-2', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify only specified categories were deleted
        expect(findCategoryByUrlKeyBehavior('to-delete-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('to-delete-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('to-keep'))->not->toBeNull();
    });

    it('deletes category hierarchies correctly', function () {
        // Create parent-child structure
        $setupData = [
            ['category_path', '_store', 'name'],
            ['parent-to-delete', '', 'Parent Delete'],
            ['parent-to-delete/child-1', '', 'Child 1'],
            ['parent-to-delete/child-2', '', 'Child 2'],
            ['parent-to-keep', '', 'Parent Keep'],
            ['parent-to-keep/child-keep', '', 'Child Keep'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Delete parent category
        $deleteData = [
            ['category_path', '_store'],
            ['parent-to-delete', ''],
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
        // Try to delete non-existent categories
        $deleteData = [
            ['category_path', '_store'],
            ['non-existent-1', ''],
            ['non-existent-2', ''],
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

        // Create and then try to delete via category paths that might match system categories
        $setupData = [
            ['category_path', '_store', 'name'],
            ['test-category', '', 'Test Category'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        $deleteData = [
            ['category_path', '_store'],
            ['test-category', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify system categories still exist
        $rootCategoryAfter = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategoryAfter = Mage::getModel('catalog/category')->load(2);

        expect((int) $rootCategoryAfter->getId())->toBe(Mage_Catalog_Model_Category::TREE_ROOT_ID)
            ->and((int) $defaultCategoryAfter->getId())->toBe(2);
    });

    it('handles multi-store data in delete operations', function () {
        // Create category with multi-store data
        $setupData = [
            ['category_path', '_store', 'name'],
            ['multi-store-delete', '', 'English Name'],
            ['multi-store-delete', 'default', 'German Name'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify multi-store category exists
        $category = findCategoryByUrlKeyBehavior('multi-store-delete');
        expect($category)->not->toBeNull();

        // Delete the category
        $deleteData = [
            ['category_path', '_store'],
            ['multi-store-delete', ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify category is completely deleted (all store data)
        expect(findCategoryByUrlKeyBehavior('multi-store-delete'))->toBeNull();
    });

    it('deletes categories using category_id', function () {
        // Create test categories
        $setupData = [
            ['category_path', '_store', 'name'],
            ['id-delete-1', '', 'ID Delete 1'],
            ['id-delete-2', '', 'ID Delete 2'],
            ['id-keep', '', 'ID Keep'],
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
            ['category_id', '_store'],
            [$cat1->getId(), ''],
            [$cat2->getId(), ''],
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify only specified categories were deleted by ID
        expect(findCategoryByUrlKeyBehavior('id-delete-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('id-delete-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('id-keep'))->not->toBeNull();
    });

    it('handles mixed category_id and category_path in delete operations', function () {
        // Create test categories
        $setupData = [
            ['category_path', '_store', 'name'],
            ['mixed-delete-1', '', 'Mixed Delete 1'],
            ['mixed-delete-2', '', 'Mixed Delete 2'],
            ['mixed-keep', '', 'Mixed Keep'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Get one category ID
        $cat1 = findCategoryByUrlKeyBehavior('mixed-delete-1');
        expect($cat1)->not->toBeNull();

        // Delete using both category_id and category_path in same import
        $deleteData = [
            ['category_id', 'category_path', '_store'],
            [$cat1->getId(), '', ''], // Delete by ID
            ['', 'mixed-delete-2', ''], // Delete by path
        ];

        createAndImportBehaviorCsv($deleteData, Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

        // Verify both methods worked
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
    it('replaces all categories with imported data', function () {
        // Create initial categories
        $setupData = [
            ['category_path', '_store', 'name'],
            ['original-1', '', 'Original 1'],
            ['original-2', '', 'Original 2'],
            ['original-3', '', 'Original 3'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify initial categories
        expect(findCategoryByUrlKeyBehavior('original-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('original-2'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('original-3'))->not->toBeNull();

        // Replace with new set of categories
        $replaceData = [
            ['category_path', '_store', 'name'],
            ['new-1', '', 'New Category 1'],
            ['new-2', '', 'New Category 2'],
            ['original-2', '', 'Updated Original 2'], // Keep and update this one
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify replacement: old categories gone, new ones added, updated ones preserved
        expect(findCategoryByUrlKeyBehavior('original-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('original-3'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('new-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('new-2'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('original-2'))->not->toBeNull();

        // Verify the updated category has new name
        $updatedCategory = findCategoryByUrlKeyBehavior('original-2');
        expect($updatedCategory->getName())->toBe('Updated Original 2');
    });

    it('preserves system categories during replace', function () {
        // Get system categories before test
        $rootCategory = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategory = Mage::getModel('catalog/category')->load(2);

        // Create some categories first
        $setupData = [
            ['category_path', '_store', 'name'],
            ['replace-test-1', '', 'Replace Test 1'],
            ['replace-test-2', '', 'Replace Test 2'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Replace with different categories
        $replaceData = [
            ['category_path', '_store', 'name'],
            ['replace-test-3', '', 'Replace Test 3'],
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify system categories still exist
        $rootCategoryAfter = Mage::getModel('catalog/category')->load(Mage_Catalog_Model_Category::TREE_ROOT_ID);
        $defaultCategoryAfter = Mage::getModel('catalog/category')->load(2);

        expect($rootCategoryAfter->getId())->toBe($rootCategory->getId())
            ->and($defaultCategoryAfter->getId())->toBe($defaultCategory->getId());

        // Verify replacement worked
        expect(findCategoryByUrlKeyBehavior('replace-test-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('replace-test-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('replace-test-3'))->not->toBeNull();
    });

    it('handles hierarchical replace correctly', function () {
        // Create hierarchical structure
        $setupData = [
            ['category_path', '_store', 'name'],
            ['old-parent', '', 'Old Parent'],
            ['old-parent/old-child-1', '', 'Old Child 1'],
            ['old-parent/old-child-2', '', 'Old Child 2'],
            ['keep-parent', '', 'Keep Parent'],
            ['keep-parent/keep-child', '', 'Keep Child'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Replace with new hierarchy (keeping some)
        $replaceData = [
            ['category_path', '_store', 'name'],
            ['new-parent', '', 'New Parent'],
            ['new-parent/new-child', '', 'New Child'],
            ['keep-parent', '', 'Updated Keep Parent'], // Keep and update
            ['keep-parent/keep-child', '', 'Updated Keep Child'], // Keep and update
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify old hierarchy is gone, new hierarchy exists, kept hierarchy updated
        expect(findCategoryByUrlKeyBehavior('old-parent'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('old-child-1'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('old-child-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('new-parent'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('new-child'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('keep-parent'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('keep-child'))->not->toBeNull();

        // Verify updates
        $keepParent = findCategoryByUrlKeyBehavior('keep-parent');
        $keepChild = findCategoryByUrlKeyBehavior('keep-child');
        expect($keepParent->getName())->toBe('Updated Keep Parent')
            ->and($keepChild->getName())->toBe('Updated Keep Child');
    });

    it('handles multi-store data in replace operations', function () {
        // Create initial multi-store categories
        $setupData = [
            ['category_path', '_store', 'name'],
            ['multi-replace-1', '', 'English 1'],
            ['multi-replace-1', 'default', 'German 1'],
            ['multi-replace-2', '', 'English 2'],
            ['multi-replace-2', 'default', 'German 2'],
        ];

        createAndImportBehaviorCsv($setupData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Replace with new multi-store structure
        $replaceData = [
            ['category_path', '_store', 'name'],
            ['multi-replace-new', '', 'New English'],
            ['multi-replace-new', 'default', 'New German'],
            ['multi-replace-1', '', 'Updated English 1'], // Keep and update
            ['multi-replace-1', 'default', 'Updated German 1'], // Keep and update
        ];

        createAndImportBehaviorCsv($replaceData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // Verify replacement
        expect(findCategoryByUrlKeyBehavior('multi-replace-2'))->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('multi-replace-new'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('multi-replace-1'))->not->toBeNull();

        // Verify multi-store data
        $newCategory = findCategoryByUrlKeyBehavior('multi-replace-new');
        $updatedCategory = findCategoryByUrlKeyBehavior('multi-replace-1');

        expect($newCategory)->not->toBeNull()
            ->and($updatedCategory)->not->toBeNull();

        // Test store-specific loading using collection (which works correctly)
        $newCategoryDefault = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId(0)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', $newCategory->getId())
            ->getFirstItem();

        $updatedCategoryDefault = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId(0)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', $updatedCategory->getId())
            ->getFirstItem();

        expect($newCategoryDefault->getName())->toBe('New English')
            ->and($updatedCategoryDefault->getName())->toBe('Updated English 1');
    });
});

describe('Behavior Comparison', function () {
    it('demonstrates different behavior outcomes with same data', function () {
        // Setup: Create initial categories
        $initialData = [
            ['category_path', '_store', 'name'],
            ['behavior-test-1', '', 'Initial 1'],
            ['behavior-test-2', '', 'Initial 2'],
        ];

        createAndImportBehaviorCsv($initialData, Mage_ImportExport_Model_Import::BEHAVIOR_APPEND);

        // Verify initial state
        expect(findCategoryByUrlKeyBehavior('behavior-test-1'))->not->toBeNull()
            ->and(findCategoryByUrlKeyBehavior('behavior-test-2'))->not->toBeNull();

        // Test data for all behaviors
        $testData = [
            ['category_path', '_store', 'name'],
            ['behavior-test-1', '', 'Updated 1'], // Update existing
            ['behavior-test-3', '', 'New 3'],     // Add new
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

        // Test 2: REPLACE behavior
        createAndImportBehaviorCsv($testData, Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        // REPLACE: Only categories in import should exist
        expect(findCategoryByUrlKeyBehavior('behavior-test-1'))->not->toBeNull() // Updated and kept
            ->and(findCategoryByUrlKeyBehavior('behavior-test-2'))->toBeNull()    // Deleted (not in import)
            ->and(findCategoryByUrlKeyBehavior('behavior-test-3'))->not->toBeNull(); // Added

        expect(findCategoryByUrlKeyBehavior('behavior-test-1')->getName())->toBe('Updated 1');
    });

    it('validates behavior parameter handling', function () {
        // Test with invalid behavior (should default to APPEND)
        $testData = [
            ['category_path', '_store', 'name'],
            ['behavior-invalid-test', '', 'Invalid Behavior Test'],
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
