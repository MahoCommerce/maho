<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Integration tests for FeedManager Writers
 * Tests actual file output for CSV, JSON, and XML formats
 */
describe('FeedManager Writer Integration', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/feedmanager_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Sample product data for testing
        $this->sampleProducts = [
            [
                'id' => '1001',
                'title' => 'Test Product One',
                'description' => 'A great product with <special> characters & symbols',
                'price' => '29.99 AUD',
                'link' => 'https://example.com/product-one',
                'image_link' => 'https://example.com/images/product-one.jpg',
                'availability' => 'in stock',
                'brand' => 'TestBrand',
            ],
            [
                'id' => '1002',
                'title' => 'Test Product Two',
                'description' => 'Another product with "quotes" and special chars',
                'price' => '49.99 AUD',
                'link' => 'https://example.com/product-two',
                'image_link' => 'https://example.com/images/product-two.jpg',
                'availability' => 'out of stock',
                'brand' => 'OtherBrand',
            ],
            [
                'id' => '1003',
                'title' => 'Product with Unicode: Ñ é ü ö',
                'description' => 'Testing unicode characters: 日本語 中文',
                'price' => '99.00 AUD',
                'link' => 'https://example.com/product-three',
                'image_link' => 'https://example.com/images/product-three.jpg',
                'availability' => 'in stock',
                'brand' => 'UnicodeBrand™',
            ],
        ];
    });

    afterEach(function () {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    });

    describe('XML Writer Output', function () {

        test('generates valid XML with correct structure', function () {
            $filePath = $this->tempDir . '/test_feed.xml';
            $writer = new Maho_FeedManager_Model_Writer_Xml();

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);

            // Check XML declaration
            expect($content)->toContain('<?xml version="1.0" encoding="UTF-8"?>');

            // Check root element
            expect($content)->toContain('<feed>');
            expect($content)->toContain('</feed>');

            // Check items
            expect($content)->toContain('<item>');
            expect($content)->toContain('</item>');

            // Validate XML is well-formed
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($content);
            expect($doc)->not->toBeFalse('XML should be well-formed');
            libxml_clear_errors();
        });

        test('properly escapes special XML characters', function () {
            $filePath = $this->tempDir . '/test_escape.xml';
            $writer = new Maho_FeedManager_Model_Writer_Xml();

            $writer->open($filePath);
            $writer->writeProduct($this->sampleProducts[0]); // Has <special> & symbols
            $writer->close();

            $content = file_get_contents($filePath);

            // Should escape < > &
            expect($content)->toContain('&lt;special&gt;');
            expect($content)->toContain('&amp;');

            // Should NOT contain unescaped special chars in content
            expect($content)->not->toMatch('/<description>.*<special>.*<\/description>/');
        });

        test('handles unicode characters correctly', function () {
            $filePath = $this->tempDir . '/test_unicode.xml';
            $writer = new Maho_FeedManager_Model_Writer_Xml();

            $writer->open($filePath);
            $writer->writeProduct($this->sampleProducts[2]); // Has unicode
            $writer->close();

            $content = file_get_contents($filePath);

            // Unicode should be preserved
            expect($content)->toContain('Ñ é ü ö');
            expect($content)->toContain('日本語');
            expect($content)->toContain('中文');
            expect($content)->toContain('™');

            // Should still be valid XML
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($content);
            expect($doc)->not->toBeFalse('XML with unicode should be well-formed');
            libxml_clear_errors();
        });

        test('generates correct number of items', function () {
            $filePath = $this->tempDir . '/test_count.xml';
            $writer = new Maho_FeedManager_Model_Writer_Xml();

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            $content = file_get_contents($filePath);
            $itemCount = substr_count($content, '<item>');

            expect($itemCount)->toBe(3);
        });
    });

    describe('CSV Writer Output', function () {

        test('generates valid CSV with header row', function () {
            $filePath = $this->tempDir . '/test_feed.csv';
            $writer = new Maho_FeedManager_Model_Writer_Csv();

            // Configure columns using setColumns
            $columns = [
                ['name' => 'id', 'source_type' => 'attribute', 'source_value' => 'id'],
                ['name' => 'title', 'source_type' => 'attribute', 'source_value' => 'title'],
                ['name' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];
            $writer->setColumns($columns);
            $writer->setIncludeHeader(true);

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            expect(file_exists($filePath))->toBeTrue();

            $lines = file($filePath, FILE_IGNORE_NEW_LINES);

            // Check header row
            expect($lines[0])->toBe('id,title,price');

            // Check data rows (should have header + 3 products)
            expect(count($lines))->toBe(4);
        });

        test('properly quotes fields with special characters', function () {
            $filePath = $this->tempDir . '/test_quotes.csv';
            $writer = new Maho_FeedManager_Model_Writer_Csv();

            $columns = [
                ['name' => 'id', 'source_type' => 'attribute', 'source_value' => 'id'],
                ['name' => 'description', 'source_type' => 'attribute', 'source_value' => 'description'],
            ];
            $writer->setColumns($columns);
            $writer->setIncludeHeader(false);

            $writer->open($filePath);
            $writer->writeProduct($this->sampleProducts[1]); // Has "quotes"
            $writer->close();

            $content = file_get_contents($filePath);

            // Description with quotes should be properly escaped
            // CSV escapes quotes by doubling them: "word" becomes ""word""
            expect($content)->toContain('""quotes""');
        });

        test('handles unicode in CSV', function () {
            $filePath = $this->tempDir . '/test_unicode.csv';
            $writer = new Maho_FeedManager_Model_Writer_Csv();

            $columns = [
                ['name' => 'id', 'source_type' => 'attribute', 'source_value' => 'id'],
                ['name' => 'title', 'source_type' => 'attribute', 'source_value' => 'title'],
            ];
            $writer->setColumns($columns);
            $writer->setIncludeHeader(false);

            $writer->open($filePath);
            $writer->writeProduct($this->sampleProducts[2]); // Has unicode
            $writer->close();

            $content = file_get_contents($filePath);

            expect($content)->toContain('Ñ é ü ö');
        });
    });

    describe('JSON Writer Output', function () {

        test('generates valid JSON with correct structure', function () {
            $filePath = $this->tempDir . '/test_feed.json';
            $writer = new Maho_FeedManager_Model_Writer_Json();

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);

            // Should be valid JSON
            $decoded = json_decode($content, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);

            // Should have products key
            expect($decoded)->toHaveKey('products');
            expect($decoded['products'])->toBeArray();
            expect(count($decoded['products']))->toBe(3);
        });

        test('properly escapes special JSON characters', function () {
            $filePath = $this->tempDir . '/test_escape.json';
            $writer = new Maho_FeedManager_Model_Writer_Json();

            $writer->open($filePath);
            $writer->writeProduct($this->sampleProducts[1]); // Has "quotes"
            $writer->close();

            $content = file_get_contents($filePath);

            // Should be valid JSON (quotes are escaped with backslash)
            $decoded = json_decode($content, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);

            // The description should contain quotes
            expect($decoded['products'][0]['description'])->toContain('"quotes"');
        });

        test('handles unicode in JSON', function () {
            $filePath = $this->tempDir . '/test_unicode.json';
            $writer = new Maho_FeedManager_Model_Writer_Json();

            $writer->open($filePath);
            $writer->writeProduct($this->sampleProducts[2]);
            $writer->close();

            $content = file_get_contents($filePath);

            $decoded = json_decode($content, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);

            // Unicode should be preserved
            expect($decoded['products'][0]['title'])->toContain('Ñ é ü ö');
            expect($decoded['products'][0]['description'])->toContain('日本語');
        });

        test('generates correct array of products', function () {
            $filePath = $this->tempDir . '/test_array.json';
            $writer = new Maho_FeedManager_Model_Writer_Json();

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            $content = file_get_contents($filePath);
            $decoded = json_decode($content, true);

            // Verify each product
            expect($decoded['products'][0]['id'])->toBe('1001');
            expect($decoded['products'][1]['id'])->toBe('1002');
            expect($decoded['products'][2]['id'])->toBe('1003');

            // Verify all fields present
            expect($decoded['products'][0])->toHaveKeys([
                'id', 'title', 'description', 'price', 'link', 'image_link', 'availability', 'brand'
            ]);
        });
    });

    describe('CSV Writer with Platform', function () {

        test('custom columns are not overwritten when opened with platform', function () {
            $filePath = $this->tempDir . '/test_columns_preserved.csv';
            $writer = new Maho_FeedManager_Model_Writer_Csv();

            // Set custom columns BEFORE opening (simulates configureFromFeed)
            $customColumns = [
                ['name' => 'sku', 'source_type' => 'attribute', 'source_value' => 'sku'],
                ['name' => 'name', 'source_type' => 'attribute', 'source_value' => 'name'],
                ['name' => 'custom_price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];
            $writer->setColumns($customColumns);
            $writer->setIncludeHeader(true);

            // Get a platform that has many more attributes
            $platform = Maho_FeedManager_Model_Platform::getAdapter('google');

            // Open WITH platform - headers should NOT be overwritten
            $writer->open($filePath, $platform);

            // Write a product
            $writer->writeProduct([
                'sku' => 'TEST123',
                'name' => 'Test Product',
                'custom_price' => '99.99',
            ]);
            $writer->close();

            $content = file_get_contents($filePath);
            $lines = explode("\n", trim($content));

            // Header should match custom columns, NOT platform attributes
            expect($lines[0])->toBe('sku,name,custom_price');

            // Should NOT contain platform-specific headers like 'google_product_category'
            expect($lines[0])->not->toContain('google_product_category');
            expect($lines[0])->not->toContain('availability');
            expect($lines[0])->not->toContain('image_link');
        });

        test('platform headers are used when no custom columns set', function () {
            $filePath = $this->tempDir . '/test_platform_headers.csv';
            $writer = new Maho_FeedManager_Model_Writer_Csv();
            $writer->setIncludeHeader(true);

            // Get a platform - don't set custom columns
            $platform = Maho_FeedManager_Model_Platform::getAdapter('google');

            // Open WITH platform - should use platform headers
            $writer->open($filePath, $platform);

            // Write a product with platform attributes
            $writer->writeProduct([
                'id' => 'TEST123',
                'title' => 'Test Product',
                'description' => 'A test product',
                'link' => 'https://example.com',
                'image_link' => 'https://example.com/image.jpg',
                'availability' => 'in_stock',
                'price' => '99.99 USD',
                'brand' => 'TestBrand',
                'google_product_category' => 'Apparel',
            ]);
            $writer->close();

            $content = file_get_contents($filePath);
            $lines = explode("\n", trim($content));

            // Header should contain platform attributes
            expect($lines[0])->toContain('google_product_category');
            expect($lines[0])->toContain('availability');
        });
    });

    describe('Validator Integration', function () {

        test('validates generated XML file', function () {
            $filePath = $this->tempDir . '/validate_test.xml';
            $writer = new Maho_FeedManager_Model_Writer_Xml();

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            $validator = new Maho_FeedManager_Model_Validator();
            $result = $validator->validate($filePath, 'xml');

            expect($result)->toBeTrue();
            expect($validator->getErrors())->toBeEmpty();
        });

        test('validates generated JSON file', function () {
            $filePath = $this->tempDir . '/validate_test.json';
            $writer = new Maho_FeedManager_Model_Writer_Json();

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            $validator = new Maho_FeedManager_Model_Validator();
            $result = $validator->validate($filePath, 'json');

            expect($result)->toBeTrue();
            expect($validator->getErrors())->toBeEmpty();
        });

        test('validates generated CSV file', function () {
            $filePath = $this->tempDir . '/validate_test.csv';
            $writer = new Maho_FeedManager_Model_Writer_Csv();

            $columns = [
                ['name' => 'id', 'source_type' => 'attribute', 'source_value' => 'id'],
                ['name' => 'title', 'source_type' => 'attribute', 'source_value' => 'title'],
            ];
            $writer->setColumns($columns);
            $writer->setIncludeHeader(true);

            $writer->open($filePath);
            foreach ($this->sampleProducts as $product) {
                $writer->writeProduct($product);
            }
            $writer->close();

            $validator = new Maho_FeedManager_Model_Validator();
            $result = $validator->validate($filePath, 'csv');

            expect($result)->toBeTrue();
            expect($validator->getErrors())->toBeEmpty();
        });
    });
});
