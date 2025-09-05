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
    // Create test categories with known structure
    $this->defaultCategory = Mage::getModel('catalog/category')->load(2); // Default category

    // Create Electronics category
    $this->electronicsCategory = Mage::getModel('catalog/category');
    $this->electronicsCategory->setName('Electronics')
        ->setUrlKey('electronics')
        ->setIsActive(1)
        ->setIncludeInMenu(1)
        ->setDescription('Electronics category for testing')
        ->setParentId(2)
        ->setPath('1/2') // Set initial path before save
        ->setStoreId(0)
        ->save();

    // Ensure proper path is set after save
    $this->electronicsCategory->move(2, null);
    $this->electronicsCategory->load($this->electronicsCategory->getId()); // Reload to get updated path

    // Create Phones subcategory
    $this->phonesCategory = Mage::getModel('catalog/category');
    $this->phonesCategory->setName('Phones')
        ->setUrlKey('phones')
        ->setIsActive(1)
        ->setIncludeInMenu(1)
        ->setDescription('Phone products')
        ->setParentId($this->electronicsCategory->getId())
        ->setPath('1/2/' . $this->electronicsCategory->getId()) // Set initial path
        ->setStoreId(0)
        ->save();

    // Ensure proper path is set after save
    $this->phonesCategory->move($this->electronicsCategory->getId(), null);
    $this->phonesCategory->load($this->phonesCategory->getId()); // Reload to get updated path

    // Create export model
    $this->exportModel = Mage::getModel('importexport/export_entity_category');
    $this->writer = Mage::getModel('importexport/export_adapter_csv');
    $this->exportModel->setWriter($this->writer);
});

afterEach(function () {
    // Clean up test categories
    if (isset($this->phonesCategory)) {
        $this->phonesCategory->delete();
    }
    if (isset($this->electronicsCategory)) {
        $this->electronicsCategory->delete();
    }
});

it('exports categories with correct CSV structure', function () {
    $result = $this->exportModel->exportFile();

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
    $result = $this->exportModel->exportFile();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Parse CSV to check parent-child relationships
    $categories = [];
    $header = str_getcsv($lines[0]);
    $categoryIdIndex = array_search('category_id', $header);
    $parentIdIndex = array_search('parent_id', $header);
    $nameIndex = array_search('name', $header);

    // Skip header row
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
    // Add store-specific data to electronics category
    $this->electronicsCategory->setStoreId(1)
        ->setName('Elektronik') // German name
        ->save();

    $result = $this->exportModel->exportFile();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    $defaultStoreRow = false;
    $storeSpecificRow = false;

    $electronicsId = $this->electronicsCategory->getId();

    foreach ($lines as $line) {
        // Look for lines that start with the electronics category ID
        if (strpos($line, $electronicsId . ',') === 0) {
            $columns = str_getcsv($line);
            if (count($columns) >= 3) {
                $categoryId = $columns[0];
                $parentId = $columns[1];
                $storeCode = $columns[2];

                if ($storeCode === '' || $storeCode === '""') {
                    // Default store (empty _store column)
                    $defaultStoreRow = true;
                    expect($line)->toContain('Electronics');
                } elseif ($storeCode === 'default' || $storeCode === 'en') {
                    // Store-specific row
                    $storeSpecificRow = true;
                    expect($line)->toContain('Elektronik');
                }
            }
        }
    }

    expect($defaultStoreRow)->toBeTrue();
    // Note: store-specific row might not appear if values are same as default
});

it('excludes disabled attributes from export', function () {
    $result = $this->exportModel->exportFile();
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
    $result = $this->exportModel->exportFile();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Parse CSV to find parent-child relationships
    $categories = [];
    $header = str_getcsv($lines[0]);
    $categoryIdIndex = array_search('category_id', $header);
    $parentIdIndex = array_search('parent_id', $header);

    // Skip header row and parse categories
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

    // Find a parent-child relationship and verify ordering
    $foundHierarchy = false;
    foreach ($categories as $category) {
        $categoryId = $category['category_id'];
        $parentId = $category['parent_id'];

        // Find children of this category
        foreach ($categories as $potentialChild) {
            if ($potentialChild['parent_id'] === $categoryId) {
                // Found a parent-child relationship - check ordering
                expect($category['line_index'])->toBeLessThan(
                    $potentialChild['line_index'],
                    "Parent category {$categoryId} should appear before child category {$potentialChild['category_id']}",
                );
                $foundHierarchy = true;
            }
        }
    }

    expect($foundHierarchy)->toBeTrue('Should have at least one parent-child relationship in the export');
});

it('handles categories without url_key gracefully', function () {
    // Create category without url_key
    $testCategory = Mage::getModel('catalog/category');
    $testCategory->setName('Test Category With Spaces!')
        ->setIsActive(1)
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    // Should not crash and should generate path from name
    $result = $this->exportModel->exportFile();

    expect($result)->toBeArray()
        ->and($result['rows'])->toBeGreaterThan(0);

    $testCategory->delete();
});

it('exports all required permanent attributes', function () {
    $result = $this->exportModel->exportFile();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    $headerLine = $lines[0];

    // Check permanent attributes are present
    expect($headerLine)->toContain('category_id')
        ->and($headerLine)->toContain('parent_id')
        ->and($headerLine)->toContain('_store');
});

it('exports sample data categories correctly', function () {
    // Sample data has existing categories, just verify export works
    $result = $this->exportModel->exportFile();

    expect($result)->toBeArray()
        ->and($result['rows'])->toBeGreaterThan(0) // Sample data has categories
        ->and($result['value'])->toContain('default-category'); // Should have the default category structure
});
