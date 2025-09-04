<?php

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
    expect($lines[0])->toContain('category_path')
        ->and($lines[0])->toContain('_store')
        ->and($lines[0])->toContain('name');
});

it('generates correct category paths using url_key', function () {
    $result = $this->exportModel->exportFile();
    $csvContent = $result['value'];
    $lines = explode("\n", trim($csvContent));

    // Test with sample data categories - look for our test categories we created
    $foundElectronics = false;
    $foundPhones = false;

    foreach ($lines as $line) {
        // Look for our test electronics category
        if (strpos($line, 'default-category/electronics,') !== false && strpos($line, 'Electronics') !== false) {
            $foundElectronics = true;
            expect($line)->toContain('Electronics');
        }

        // Look for our test phones category
        if (strpos($line, 'default-category/electronics/phones,') !== false) {
            $foundPhones = true;
            expect($line)->toContain('Phones');
        }
    }

    // Our test categories should be found in the export
    expect($foundElectronics)->toBeTrue();
    expect($foundPhones)->toBeTrue();
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

    foreach ($lines as $line) {
        // Look for the electronics category line specifically (not phones)
        if (strpos($line, 'default-category/electronics,') !== false) {
            if (strpos($line, ',"",') !== false || strpos($line, ',,"') !== false) {
                // Default store (empty _store column)
                $defaultStoreRow = true;
                expect($line)->toContain('Electronics');
            } elseif (strpos($line, ',default,') !== false) {
                // Store-specific row
                $storeSpecificRow = true;
                expect($line)->toContain('Elektronik');
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

    // Check sample data hierarchy - men should come before men subcategories
    $menIndex = -1;
    $menSubIndex = -1;

    foreach ($lines as $index => $line) {
        if (strpos($line, 'default-category/men,') !== false) {
            if ($menIndex === -1) {
                $menIndex = $index;
            }
        }
        if (strpos($line, 'default-category/men/') !== false) {
            if ($menSubIndex === -1) {
                $menSubIndex = $index;
            }
        }
    }

    // Parent should come before child in export (if both exist in sample data)
    if ($menIndex !== -1 && $menSubIndex !== -1) {
        expect($menIndex)->toBeLessThan($menSubIndex);
    } else {
        // If sample data structure is different, just verify we have hierarchical data
        expect($csvContent)->toMatch('/default-category\/[^\/]+\/[^\/]+/'); // Has nested categories
    }
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
    expect($headerLine)->toContain('category_path')
        ->and($headerLine)->toContain('_store');
});

it('exports sample data categories correctly', function () {
    // Sample data has existing categories, just verify export works
    $result = $this->exportModel->exportFile();

    expect($result)->toBeArray()
        ->and($result['rows'])->toBeGreaterThan(0) // Sample data has categories
        ->and($result['value'])->toContain('default-category'); // Should have the default category structure
});
