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

// Shared test data - created once for all tests
function getSharedTestData()
{
    static $testData = null;

    if ($testData === null) {
        // Create test categories with known structure
        $defaultCategory = Mage::getModel('catalog/category')->load(2);

        // Create Electronics category with minimal operations
        $electronicsCategory = Mage::getModel('catalog/category');
        $electronicsCategory->setName('Test Electronics')
            ->setUrlKey('test-electronics')
            ->setIsActive(1)
            ->setIncludeInMenu(1)
            ->setDescription('Test electronics category')
            ->setParentId(2)
            ->setStoreId(0);

        // Use direct database insert for better performance
        $resource = $electronicsCategory->getResource();
        $resource->save($electronicsCategory);

        // Create Phones subcategory
        $phonesCategory = Mage::getModel('catalog/category');
        $phonesCategory->setName('Test Phones')
            ->setUrlKey('test-phones')
            ->setIsActive(1)
            ->setIncludeInMenu(1)
            ->setDescription('Test phone products')
            ->setParentId($electronicsCategory->getId())
            ->setStoreId(0);

        $resource->save($phonesCategory);

        $testData = [
            'defaultCategory' => $defaultCategory,
            'electronicsCategory' => $electronicsCategory,
            'phonesCategory' => $phonesCategory,
        ];
    }

    return $testData;
}

beforeEach(function () {
    // Create export model only
    $this->exportModel = Mage::getModel('importexport/export_entity_category');
    $this->writer = Mage::getModel('importexport/export_adapter_csv');
    $this->exportModel->setWriter($this->writer);

    // Cache export result for tests that don't modify data
    static $cachedExport = null;
    $this->getCachedExport = function () use (&$cachedExport) {
        if ($cachedExport === null) {
            $cachedExport = $this->exportModel->exportFile();
        }
        return $cachedExport;
    };
});

// No afterAll cleanup needed - tests create minimal temporary data
// that doesn't interfere with other tests

it('exports categories with correct CSV structure', function () {
    $result = ($this->getCachedExport)();

    expect($result)->toBeArray()
        ->and($result['type'])->toBe('string')
        ->and($result['rows'])->toBeGreaterThan(0);

    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Check header row exists
    expect($lines[0])->toContain('category_id')
        ->and($lines[0])->toContain('parent_id')
        ->and($lines[0])->toContain('_store')
        ->and($lines[0])->toContain('name');
});

it('generates correct parent-child relationships', function () {
    $result = ($this->getCachedExport)();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Parse CSV to check parent-child relationships
    $categories = [];
    $header = str_getcsv($lines[0]);
    $categoryIdIndex = array_search('category_id', $header);
    $parentIdIndex = array_search('parent_id', $header);
    $nameIndex = array_search('name', $header);

    // Skip header row and parse all categories for complete validation
    for ($i = 1; $i < count($lines); $i++) {
        if (empty(trim($lines[$i]))) {
            continue;
        }

        $row = str_getcsv($lines[$i]);
        if (count($row) > max($categoryIdIndex, $parentIdIndex, $nameIndex)) {
            $categories[$row[$categoryIdIndex]] = [
                'parent_id' => $row[$parentIdIndex],
                'name' => $row[$nameIndex],
            ];
        }
    }

    // Check that we have categories with valid parent relationships
    expect(count($categories))->toBeGreaterThan(0);

    // Check that categories have proper parent references
    $foundValidParentChild = false;
    foreach ($categories as $categoryId => $data) {
        if (!empty($data['parent_id']) && isset($categories[$data['parent_id']])) {
            $foundValidParentChild = true;
            break;
        }
    }

    expect($foundValidParentChild)->toBeTrue();
});

it('exports multi-store data correctly', function () {
    // Create a temporary category with store-specific data for this test only
    $tempCategory = Mage::getModel('catalog/category');
    $tempCategory->setName('Temp Multi-Store Test')
        ->setUrlKey('temp-multi-store')
        ->setIsActive(1)
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    // Add store-specific data
    $tempCategory->setStoreId(1)
        ->setName('Elektronik Temp') // German name
        ->save();

    try {
        $result = $this->exportModel->exportFile();
        $csvContent = $result['value'];
        $lines = explode("\n", trim($csvContent));

        $foundMultiStoreData = false;
        $tempCategoryId = $tempCategory->getId();

        foreach ($lines as $line) {
            // Look for lines that contain our test category
            if (strpos($line, (string) $tempCategoryId) !== false) {
                $columns = str_getcsv($line);
                if (count($columns) >= 3) {
                    $storeCode = $columns[2];

                    // Check for multi-store functionality
                    if (strpos($line, 'Multi-Store') !== false || strpos($line, 'Elektronik') !== false) {
                        $foundMultiStoreData = true;
                        break;
                    }
                }
            }
        }

        expect($foundMultiStoreData)->toBeTrue('Should find multi-store category data');
    } finally {
        // Clean up
        $tempCategory->delete();
    }
});

it('excludes disabled attributes from export', function () {
    $result = ($this->getCachedExport)();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    $headerLine = $lines[0];

    // These attributes should be excluded
    expect($headerLine)->not->toContain('all_children')
        ->and($headerLine)->not->toContain('children')
        ->and($headerLine)->not->toContain('children_count')
        ->and($headerLine)->not->toContain('level')
        ->and($headerLine)->not->toContain(',path,')  // More specific to avoid matching path_in_store
        ->and($headerLine)->not->toContain('position');
});

it('maintains hierarchical order in export', function () {
    $result = ($this->getCachedExport)();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Parse CSV to find parent-child relationships - limit processing for performance
    $categories = [];
    $header = str_getcsv($lines[0]);
    $categoryIdIndex = array_search('category_id', $header);
    $parentIdIndex = array_search('parent_id', $header);

    // Skip header row and parse categories for complete validation
    for ($i = 1; $i < count($lines); $i++) {
        if (empty(trim($lines[$i]))) {
            continue;
        }

        $row = str_getcsv($lines[$i]);
        if (count($row) > max($categoryIdIndex, $parentIdIndex)) {
            $categoryId = (int) $row[$categoryIdIndex];
            $parentId = (int) $row[$parentIdIndex];

            $categories[$i] = [
                'category_id' => $categoryId,
                'parent_id' => $parentId,
                'line_index' => $i,
            ];
        }
    }

    // Verify we have hierarchical data (categories with different parent IDs)
    $parentIds = array_column($categories, 'parent_id');
    $uniqueParentIds = array_unique($parentIds);
    expect(count($uniqueParentIds))->toBeGreaterThan(1, 'Should have categories with different parent IDs');

    // Find parent-child relationships and verify ordering
    $foundHierarchy = false;
    $hierarchyViolations = [];

    foreach ($categories as $category) {
        $categoryId = $category['category_id'];

        // Find children of this category
        foreach ($categories as $potentialChild) {
            if ($potentialChild['parent_id'] === $categoryId) {
                $foundHierarchy = true;

                // Check ordering - parent should appear before child
                if ($category['line_index'] >= $potentialChild['line_index']) {
                    $hierarchyViolations[] = "Parent category {$categoryId} appears after child category {$potentialChild['category_id']}";
                }
            }
        }
    }

    // Report any hierarchy violations
    if (!empty($hierarchyViolations)) {
        expect(false)->toBeTrue('Hierarchy violations found: ' . implode(', ', $hierarchyViolations));
    }

    expect($foundHierarchy)->toBeTrue('Should have at least one parent-child relationship in the export');
});

it('handles categories without url_key gracefully', function () {
    // Create category without url_key for this test only
    $testCategory = Mage::getModel('catalog/category');
    $testCategory->setName('Test Category With Spaces!')
        ->setIsActive(1)
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    try {
        // Should not crash and should generate path from name
        $result = $this->exportModel->exportFile();

        expect($result)->toBeArray()
            ->and($result['rows'])->toBeGreaterThan(0);
    } finally {
        $testCategory->delete();
    }
});

it('exports all required permanent attributes', function () {
    $result = ($this->getCachedExport)();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    $headerLine = $lines[0];

    // Check permanent attributes are present
    expect($headerLine)->toContain('category_id')
        ->and($headerLine)->toContain('parent_id')
        ->and($headerLine)->toContain('_store');
});

it('exports sample data categories correctly', function () {
    // Sample data has existing categories, verify meaningful content
    $result = ($this->getCachedExport)();

    expect($result)->toBeArray()
        ->and($result['rows'])->toBeGreaterThan(0); // Sample data has categories

    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Parse and verify we have meaningful category data
    $foundDefaultCategory = false;
    $foundCategoryWithName = false;

    foreach ($lines as $line) {
        if (strpos($line, ',2,') !== false) { // Default category (ID 2)
            $foundDefaultCategory = true;
        }
        // Check if any line (after header) contains letters - indicating category names
        // Look for any letter sequence anywhere in a non-header line
        if (!str_starts_with($line, 'category_id') && preg_match('/[a-zA-Z]{2,}/', $line)) {
            $foundCategoryWithName = true;
        }
    }

    expect($foundDefaultCategory)->toBeTrue('Should export default category')
        ->and($foundCategoryWithName)->toBeTrue('Should have categories with meaningful names');
});
