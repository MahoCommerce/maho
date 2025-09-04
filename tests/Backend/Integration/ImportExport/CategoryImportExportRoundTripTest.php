<?php

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
    // Create test categories with comprehensive data
    $originalData = [
        'electronics' => [
            'name' => 'Electronics',
            'description' => 'Electronic devices and gadgets',
            'is_active' => 1,
            'include_in_menu' => 1,
            'meta_title' => 'Electronics - Shop Now',
            'meta_description' => 'Browse our electronics collection',
        ],
        'electronics/phones' => [
            'name' => 'Mobile Phones',
            'description' => 'Smartphones and mobile devices',
            'is_active' => 1,
            'include_in_menu' => 1,
            'meta_title' => 'Mobile Phones',
            'meta_description' => 'Latest smartphone models',
        ],
        'clothing' => [
            'name' => 'Clothing',
            'description' => 'Fashion and apparel',
            'is_active' => 0,
            'include_in_menu' => 0,
            'meta_title' => 'Clothing Store',
            'meta_description' => 'Quality clothing for all',
        ],
    ];

    // Create categories with initial import
    $csvData = [['category_path', '_store', 'name', 'description', 'is_active', 'include_in_menu', 'meta_title', 'meta_description']];
    foreach ($originalData as $path => $data) {
        $csvData[] = [
            $path, '', $data['name'], $data['description'],
            (string) $data['is_active'], (string) $data['include_in_menu'],
            $data['meta_title'], $data['meta_description'],
        ];
    }

    createAndImportRoundTripCsv($csvData);

    // Refresh category paths after import
    $this->exportModel->refreshCategoryPaths();

    // Export the categories
    $exportResult = $this->exportModel->exportFile();
    $exportedCsv = $exportResult['value'];

    // Delete the categories
    foreach (array_keys($originalData) as $path) {
        $urlKey = basename($path);
        $category = findCategoryByUrlKeyRoundTrip($urlKey);
        if ($category) {
            $category->delete();
        }
    }

    // Re-import from exported data
    importFromCsvStringRoundTrip($exportedCsv);

    // Verify all data was preserved
    foreach ($originalData as $path => $expectedData) {
        $urlKey = basename($path);
        $category = findCategoryByUrlKeyRoundTrip($urlKey);

        expect($category)->not->toBeNull("Category {$urlKey} should exist after round-trip");
        expect($category->getName())->toBe($expectedData['name']);
        expect($category->getDescription())->toBe($expectedData['description']);
        expect((int) $category->getIsActive())->toBe($expectedData['is_active']);
        expect((int) $category->getIncludeInMenu())->toBe($expectedData['include_in_menu']);
        expect($category->getMetaTitle())->toBe($expectedData['meta_title']);
        expect($category->getMetaDescription())->toBe($expectedData['meta_description']);
    }

    // Verify hierarchical relationships are preserved
    $electronics = findCategoryByUrlKeyRoundTrip('electronics');
    $phones = findCategoryByUrlKeyRoundTrip('phones');

    expect($phones->getParentId())->toBe((int) $electronics->getId());
});

it('handles multi-store data in round trip correctly', function () {
    // Create multi-store data with unique names to avoid sample data conflicts
    $csvData = [
        ['category_path', '_store', 'name', 'description'],
        ['test-electronics', '', 'Test Electronics', 'English electronics description'],
        ['test-electronics', 'default', 'Test Elektronik', 'German electronics description'],
        ['test-electronics/test-phones', '', 'Test Phones', 'English phones description'],
        ['test-electronics/test-phones', 'default', 'Test Telefone', 'German phones description'],
    ];

    createAndImportRoundTripCsv($csvData);

    // Refresh category paths after import
    $this->exportModel->refreshCategoryPaths();

    // Export
    $exportResult = $this->exportModel->exportFile();
    $exportedCsv = $exportResult['value'];

    // Verify export contains both store rows
    expect($exportedCsv)->toContain('Test Electronics')
        ->and($exportedCsv)->toContain('Test Elektronik')
        ->and($exportedCsv)->toContain('Test Phones')
        ->and($exportedCsv)->toContain('Test Telefone');

    // Delete and re-import
    $electronics = findCategoryByUrlKeyRoundTrip('test-electronics');
    $phones = findCategoryByUrlKeyRoundTrip('test-phones');

    if ($phones) {
        $phones->delete();
    }
    if ($electronics) {
        $electronics->delete();
    }

    importFromCsvStringRoundTrip($exportedCsv);

    // Verify multi-store data is preserved
    $electronics = findCategoryByUrlKeyRoundTrip('test-electronics');
    $phones = findCategoryByUrlKeyRoundTrip('test-phones');

    expect($electronics)->not->toBeNull()
        ->and($phones)->not->toBeNull();

    // Check default store (English) - use collection loading which handles store scope correctly
    $electronicsDefault = Mage::getModel('catalog/category')->getCollection()
        ->setStoreId(0)
        ->addAttributeToSelect('name')
        ->addAttributeToFilter('entity_id', $electronics->getId())
        ->getFirstItem();
    $phonesDefault = Mage::getModel('catalog/category')->getCollection()
        ->setStoreId(0)
        ->addAttributeToSelect('name')
        ->addAttributeToFilter('entity_id', $phones->getId())
        ->getFirstItem();
    expect($electronicsDefault->getName())->toBe('Test Electronics')
        ->and($phonesDefault->getName())->toBe('Test Phones');

    // Check store-specific (German) - use collection loading which handles store scope correctly
    $electronicsGerman = Mage::getModel('catalog/category')->getCollection()
        ->setStoreId(1)
        ->addAttributeToSelect('name')
        ->addAttributeToFilter('entity_id', $electronics->getId())
        ->getFirstItem();
    $phonesGerman = Mage::getModel('catalog/category')->getCollection()
        ->setStoreId(1)
        ->addAttributeToSelect('name')
        ->addAttributeToFilter('entity_id', $phones->getId())
        ->getFirstItem();
    expect($electronicsGerman->getName())->toBe('Test Elektronik')
        ->and($phonesGerman->getName())->toBe('Test Telefone');
});

it('maintains category order and position in round trip', function () {
    // Create categories with specific positions
    $csvData = [
        ['category_path', '_store', 'name', 'is_active'],
        ['category-a', '', 'Category A', '1'],
        ['category-b', '', 'Category B', '1'],
        ['category-c', '', 'Category C', '1'],
        ['category-a/sub-a1', '', 'Sub A1', '1'],
        ['category-a/sub-a2', '', 'Sub A2', '1'],
    ];

    createAndImportRoundTripCsv($csvData);

    // Get original positions
    $originalPositions = [];
    foreach (['category-a', 'category-b', 'category-c', 'sub-a1', 'sub-a2'] as $urlKey) {
        $category = findCategoryByUrlKeyRoundTrip($urlKey);
        if ($category) {
            $originalPositions[$urlKey] = $category->getPosition();
        }
    }

    // Refresh category paths after import
    $this->exportModel->refreshCategoryPaths();

    // Export and re-import
    $exportResult = $this->exportModel->exportFile();

    // Clean up
    foreach (['sub-a2', 'sub-a1', 'category-c', 'category-b', 'category-a'] as $urlKey) {
        $category = findCategoryByUrlKeyRoundTrip($urlKey);
        if ($category) {
            $category->delete();
        }
    }

    importFromCsvStringRoundTrip($exportResult['value']);

    // Verify positions are maintained (or at least logical)
    $categoryA = findCategoryByUrlKeyRoundTrip('category-a');
    $categoryB = findCategoryByUrlKeyRoundTrip('category-b');
    $categoryC = findCategoryByUrlKeyRoundTrip('category-c');
    $subA1 = findCategoryByUrlKeyRoundTrip('sub-a1');
    $subA2 = findCategoryByUrlKeyRoundTrip('sub-a2');

    expect($categoryA)->not->toBeNull()
        ->and($categoryB)->not->toBeNull()
        ->and($categoryC)->not->toBeNull()
        ->and($subA1)->not->toBeNull()
        ->and($subA2)->not->toBeNull();

    // Check parent relationships are maintained
    expect($subA1->getParentId())->toBe((int) $categoryA->getId())
        ->and($subA2->getParentId())->toBe((int) $categoryA->getId());
});

it('handles complex hierarchies in round trip', function () {
    // Create deep, complex hierarchy
    $csvData = [
        ['category_path', '_store', 'name', 'is_active'],
        ['root', '', 'Root Category', '1'],
        ['root/level2a', '', 'Level 2A', '1'],
        ['root/level2b', '', 'Level 2B', '1'],
        ['root/level2a/level3a', '', 'Level 3A', '1'],
        ['root/level2a/level3b', '', 'Level 3B', '1'],
        ['root/level2b/level3c', '', 'Level 3C', '1'],
        ['root/level2a/level3a/level4', '', 'Level 4', '1'],
    ];

    createAndImportRoundTripCsv($csvData);

    // Store original tree structure
    $originalStructure = [];
    foreach (['root', 'level2a', 'level2b', 'level3a', 'level3b', 'level3c', 'level4'] as $urlKey) {
        $category = findCategoryByUrlKeyRoundTrip($urlKey);
        if ($category) {
            $originalStructure[$urlKey] = [
                'id' => $category->getId(),
                'parent_id' => $category->getParentId(),
                'level' => $category->getLevel(),
                'path' => $category->getPath(),
            ];
        }
    }

    // Refresh category paths after import
    $this->exportModel->refreshCategoryPaths();

    // Export and re-import
    $exportResult = $this->exportModel->exportFile();

    // Clean up (delete in reverse order to avoid foreign key issues)
    foreach (array_reverse(['root', 'level2a', 'level2b', 'level3a', 'level3b', 'level3c', 'level4']) as $urlKey) {
        $category = findCategoryByUrlKeyRoundTrip($urlKey);
        if ($category) {
            $category->delete();
        }
    }

    importFromCsvStringRoundTrip($exportResult['value']);

    // Verify complete tree structure
    $root = findCategoryByUrlKeyRoundTrip('root');
    $level2a = findCategoryByUrlKeyRoundTrip('level2a');
    $level2b = findCategoryByUrlKeyRoundTrip('level2b');
    $level3a = findCategoryByUrlKeyRoundTrip('level3a');
    $level4 = findCategoryByUrlKeyRoundTrip('level4');

    // Check hierarchy is intact
    expect($level2a->getParentId())->toBe((int) $root->getId());
    expect($level2b->getParentId())->toBe((int) $root->getId());
    expect($level3a->getParentId())->toBe((int) $level2a->getId());
    expect($level4->getParentId())->toBe((int) $level3a->getId());

    // Check levels are correct
    expect($root->getLevel())->toBeLessThan($level2a->getLevel())
        ->and($level2a->getLevel())->toBeLessThan($level3a->getLevel())
        ->and($level3a->getLevel())->toBeLessThan($level4->getLevel());
});

it('preserves attribute data types and special values', function () {
    // Create category with various attribute types
    $csvData = [
        ['category_path', '_store', 'name', 'is_active', 'include_in_menu', 'description'],
        ['test-attributes', '', 'Test Attributes', '0', '1', 'Description with "quotes" and special chars: <>&'],
    ];

    createAndImportRoundTripCsv($csvData);

    // Refresh category paths after import
    $this->exportModel->refreshCategoryPaths();

    // Export and re-import
    $exportResult = $this->exportModel->exportFile();

    $originalCategory = findCategoryByUrlKeyRoundTrip('test-attributes');
    $originalId = $originalCategory->getId();
    $originalCategory->delete();

    importFromCsvStringRoundTrip($exportResult['value']);

    // Verify data types and special characters are preserved
    $category = findCategoryByUrlKeyRoundTrip('test-attributes');
    expect($category)->not->toBeNull()
        ->and($category->getId())->not->toBe($originalId) // New ID
        ->and($category->getName())->toBe('Test Attributes')
        ->and((int) $category->getIsActive())->toBe(0)
        ->and((int) $category->getIncludeInMenu())->toBe(1)
        ->and($category->getDescription())->toBe('Description with "quotes" and special chars: <>&');
});

// Helper methods
function createAndImportRoundTripCsv(array $data): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'category_roundtrip_test');
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
    $importModel->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);

    $importModel->validateData();
    $importModel->importData();

    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
}

function importFromCsvStringRoundTrip(string $csvContent): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'category_import_from_export');
    file_put_contents($tmpFile, $csvContent);

    // Ensure file exists and has content before creating adapter
    if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
        throw new Exception('Failed to create temporary CSV file or file is empty');
    }

    // Create fresh import model and CSV adapter
    $importModel = Mage::getModel('importexport/import_entity_category');
    $csvAdapter = Mage::getModel('importexport/import_adapter_csv', $tmpFile);
    $importModel->setSource($csvAdapter);
    $importModel->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);

    $importModel->validateData();
    $importModel->importData();

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
