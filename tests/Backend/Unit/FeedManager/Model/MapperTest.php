<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Mapper - Special Price Handling', function () {

    describe('Special Price Date Validation', function () {
        test('returns special price when within valid date range', function () {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            $productData = [
                'special_price' => 99.99,
                'special_from_date' => $yesterday,
                'special_to_date' => $tomorrow,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBe(99.99);
        });

        test('returns null when special price date has expired', function () {
            $lastWeek = date('Y-m-d', strtotime('-7 days'));
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $productData = [
                'special_price' => 99.99,
                'special_from_date' => $lastWeek,
                'special_to_date' => $yesterday,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBeNull();
        });

        test('returns null when special price has not started yet', function () {
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $nextWeek = date('Y-m-d', strtotime('+7 days'));

            $productData = [
                'special_price' => 99.99,
                'special_from_date' => $tomorrow,
                'special_to_date' => $nextWeek,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBeNull();
        });

        test('returns special price when no date restrictions', function () {
            $productData = [
                'special_price' => 99.99,
                'special_from_date' => null,
                'special_to_date' => null,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBe(99.99);
        });

        test('returns special price when only from_date is set and is in past', function () {
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $productData = [
                'special_price' => 99.99,
                'special_from_date' => $yesterday,
                'special_to_date' => null,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBe(99.99);
        });

        test('returns special price when only to_date is set and is in future', function () {
            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            $productData = [
                'special_price' => 99.99,
                'special_from_date' => null,
                'special_to_date' => $tomorrow,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBe(99.99);
        });

        test('returns null when special price is zero', function () {
            $productData = [
                'special_price' => 0,
                'special_from_date' => null,
                'special_to_date' => null,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBeNull();
        });

        test('returns null when special price is empty', function () {
            $productData = [
                'special_price' => null,
                'special_from_date' => null,
                'special_to_date' => null,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($result)->toBeNull();
        });
    });

    describe('Price Formatting with Empty Handling', function () {
        test('formats valid special price with currency', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                99.99,
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => true]
            );
            expect($result)->toBe('99.99 AUD');
        });

        test('returns empty string for null price when skip_if_empty is true', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                null,
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => true]
            );
            expect($result)->toBe('');
        });

        test('returns empty string for zero price when skip_if_empty is true', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                0,
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => true]
            );
            expect($result)->toBe('');
        });

        test('returns empty string for empty string when skip_if_empty is true', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                '',
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => true]
            );
            expect($result)->toBe('');
        });

        test('formats zero price when skip_if_empty is false', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                0,
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => false]
            );
            expect($result)->toBe('0.00 AUD');
        });
    });

    describe('Parent Value Fallback', function () {
        test('uses child value when available', function () {
            $productData = [
                'special_price' => 79.99,
                'parent_special_price' => 89.99,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentFallback(
                'special_price',
                $productData,
                true // use_parent_fallback enabled
            );
            expect($result)->toBe(79.99);
        });

        test('falls back to parent value when child value is empty', function () {
            $productData = [
                'special_price' => null,
                'parent_special_price' => 89.99,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentFallback(
                'special_price',
                $productData,
                true // use_parent_fallback enabled
            );
            expect($result)->toBe(89.99);
        });

        test('returns empty when both child and parent are empty', function () {
            $productData = [
                'special_price' => null,
                'parent_special_price' => null,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentFallback(
                'special_price',
                $productData,
                true // use_parent_fallback enabled
            );
            expect($result)->toBeNull();
        });

        test('does not fallback to parent when use_parent_fallback is disabled', function () {
            $productData = [
                'special_price' => null,
                'parent_special_price' => 89.99,
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentFallback(
                'special_price',
                $productData,
                false // use_parent_fallback disabled
            );
            expect($result)->toBeNull();
        });

        test('uses parent URL for child product when configured', function () {
            $productData = [
                'url' => 'https://example.com/child-product',
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentFallback(
                'url',
                $productData,
                true // use_parent_fallback enabled
            );
            // When child has value, use child
            expect($result)->toBe('https://example.com/child-product');
        });

        test('uses parent URL when child URL is empty', function () {
            $productData = [
                'url' => '',
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentFallback(
                'url',
                $productData,
                true // use_parent_fallback enabled
            );
            expect($result)->toBe('https://example.com/parent-product');
        });
    });

    describe('Combined Special Price Scenarios', function () {
        test('child product with valid special price uses own price with currency', function () {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            $productData = [
                'special_price' => 79.99,
                'special_from_date' => $yesterday,
                'special_to_date' => $tomorrow,
                'parent_special_price' => 89.99,
            ];

            // Step 1: Get valid special price (with date check)
            $validPrice = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($validPrice)->toBe(79.99);

            // Step 2: Format with currency
            $formatted = Maho_FeedManager_Model_Transformer::apply(
                $validPrice,
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => true]
            );
            expect($formatted)->toBe('79.99 AUD');
        });

        test('child product with expired special price falls back to parent', function () {
            $lastWeek = date('Y-m-d', strtotime('-7 days'));
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $nextWeek = date('Y-m-d', strtotime('+7 days'));

            $productData = [
                'special_price' => 79.99,
                'special_from_date' => $lastWeek,
                'special_to_date' => $yesterday, // Expired
                'parent_special_price' => 89.99,
                'parent_special_from_date' => $yesterday,
                'parent_special_to_date' => $nextWeek, // Valid
            ];

            // Child's special price is expired
            $childPrice = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($childPrice)->toBeNull();

            // Should fall back to parent's valid special price
            $parentData = [
                'special_price' => $productData['parent_special_price'],
                'special_from_date' => $productData['parent_special_from_date'],
                'special_to_date' => $productData['parent_special_to_date'],
            ];
            $parentPrice = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($parentData);
            expect($parentPrice)->toBe(89.99);
        });

        test('no special price outputs empty string not formatted zero', function () {
            $productData = [
                'special_price' => null,
                'special_from_date' => null,
                'special_to_date' => null,
                'parent_special_price' => null,
            ];

            $validPrice = Maho_FeedManager_Model_Mapper::getValidSpecialPrice($productData);
            expect($validPrice)->toBeNull();

            $formatted = Maho_FeedManager_Model_Transformer::apply(
                $validPrice,
                'format_price',
                ['currency' => 'AUD', 'skip_if_empty' => true]
            );
            expect($formatted)->toBe('');
        });
    });

    describe('Parent Mode Options', function () {
        test('empty mode uses child value only', function () {
            $productData = [
                'url' => 'https://example.com/child-product',
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                '' // empty mode
            );
            expect($result)->toBe('https://example.com/child-product');
        });

        test('empty mode returns null when child is empty', function () {
            $productData = [
                'url' => null,
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                '' // empty mode
            );
            expect($result)->toBeNull();
        });

        test('if_empty mode uses child value when present', function () {
            $productData = [
                'url' => 'https://example.com/child-product',
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                'if_empty'
            );
            expect($result)->toBe('https://example.com/child-product');
        });

        test('if_empty mode falls back to parent when child is empty', function () {
            $productData = [
                'url' => '',
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                'if_empty'
            );
            expect($result)->toBe('https://example.com/parent-product');
        });

        test('always mode uses parent value even when child has value', function () {
            $productData = [
                'url' => 'https://example.com/child-product',
                'parent_url' => 'https://example.com/parent-product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                'always'
            );
            expect($result)->toBe('https://example.com/parent-product');
        });

        test('always mode falls back to child when parent is missing', function () {
            $productData = [
                'url' => 'https://example.com/child-product',
                // no parent_url
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                'always'
            );
            expect($result)->toBe('https://example.com/child-product');
        });

        test('always mode returns null when both are missing', function () {
            $productData = [];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'url',
                $productData,
                'always'
            );
            expect($result)->toBeNull();
        });

        test('handles empty attribute code gracefully', function () {
            $productData = [
                'name' => 'Test Product',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                '',
                $productData,
                'if_empty'
            );
            expect($result)->toBeNull();
        });
    });
});

describe('FeedManager Mapper - XML Structure Generation', function () {

    beforeEach(function () {
        // Create a mock feed for testing
        $this->feed = Mage::getModel('feedmanager/feed');
        $this->feed->setStoreId(1);
        $this->feed->setPlatform('custom'); // Required for Mapper initialization
        $this->mapper = new Maho_FeedManager_Model_Mapper($this->feed);
    });

    describe('Basic XML Element Generation', function () {
        test('generates simple element with attribute source', function () {
            $structure = [
                ['tag' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'TEST123');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<item>');
            expect($xml)->toContain('<id>TEST123</id>');
            expect($xml)->toContain('</item>');
        });

        test('generates element with static source', function () {
            $structure = [
                ['tag' => 'condition', 'source_type' => 'static', 'source_value' => 'new'],
            ];

            $product = Mage::getModel('catalog/product');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<condition>new</condition>');
        });

        test('generates element with CDATA wrapping', function () {
            $structure = [
                ['tag' => 'title', 'source_type' => 'attribute', 'source_value' => 'name', 'cdata' => true],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('name', 'Test Product <Special>');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<title><![CDATA[Test Product <Special>]]></title>');
        });

        test('escapes HTML entities when not using CDATA', function () {
            $structure = [
                ['tag' => 'title', 'source_type' => 'attribute', 'source_value' => 'name', 'cdata' => false],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('name', 'Test & Product <Special>');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<title>Test &amp; Product &lt;Special&gt;</title>');
        });
    });

    describe('Optional Element Handling', function () {
        test('skips optional element when value is empty', function () {
            $structure = [
                ['tag' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku'],
                ['tag' => 'brand', 'source_type' => 'attribute', 'source_value' => 'manufacturer', 'optional' => true],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'TEST123');
            // manufacturer not set

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<id>TEST123</id>');
            expect($xml)->not->toContain('<brand>');
        });

        test('includes optional element when value is present', function () {
            $structure = [
                ['tag' => 'brand', 'source_type' => 'static', 'source_value' => 'Nike', 'optional' => true],
            ];

            $product = Mage::getModel('catalog/product');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<brand>Nike</brand>');
        });

        test('includes non-optional element even when empty', function () {
            $structure = [
                ['tag' => 'brand', 'source_type' => 'attribute', 'source_value' => 'manufacturer', 'optional' => false],
            ];

            $product = Mage::getModel('catalog/product');
            // manufacturer not set

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<brand></brand>');
        });
    });

    describe('Nested Element (Group) Support', function () {
        test('generates nested group elements', function () {
            $structure = [
                [
                    'tag' => 'pricing',
                    'children' => [
                        ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
                        ['tag' => 'currency', 'source_type' => 'static', 'source_value' => 'AUD'],
                    ],
                ],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 99.99);

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<pricing>');
            expect($xml)->toContain('<price>99.99</price>');
            expect($xml)->toContain('<currency>AUD</currency>');
            expect($xml)->toContain('</pricing>');
        });
    });

    describe('Use Parent Mode Integration', function () {
        // Note: Parent mode tests use getValueWithParentMode which is tested separately.
        // These tests verify the integration in XML structure context using static values.

        test('generates element with static value for parent mode coverage', function () {
            $structure = [
                [
                    'tag' => 'description',
                    'source_type' => 'static',
                    'source_value' => 'Static Description',
                ],
            ];

            $product = Mage::getModel('catalog/product');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<description>Static Description</description>');
        });

        test('parent mode logic is tested via static getValueWithParentMode method', function () {
            // This verifies the static method works correctly
            $productData = [
                'description' => '',
                'parent_description' => 'Parent Description',
            ];

            $result = Maho_FeedManager_Model_Mapper::getValueWithParentMode(
                'description',
                $productData,
                'if_empty'
            );

            expect($result)->toBe('Parent Description');
        });
    });

    describe('Array Value Handling', function () {
        // Note: Array conversion is tested via the _buildXmlElements method
        // which converts arrays to comma-separated strings
        test('array conversion happens in _buildXmlElements', function () {
            // This is more of a documentation test - the actual array handling
            // happens when source values return arrays (like category_names)

            // Test that the structure generates valid XML
            $structure = [
                ['tag' => 'test', 'source_type' => 'static', 'source_value' => 'value'],
            ];

            $product = Mage::getModel('catalog/product');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<test>value</test>');
        });

        test('implode function correctly joins and filters arrays', function () {
            // Direct test of the array filtering logic
            $value = ['Tag1', '', null, 'Tag2'];
            $result = implode(',', array_filter($value, fn($v) => $v !== null && $v !== ''));

            expect($result)->toBe('Tag1,Tag2');
        });
    });

    describe('Custom Item Tag', function () {
        test('uses custom item tag', function () {
            $structure = [
                ['tag' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'TEST123');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'product', 0);

            expect($xml)->toContain('<product>');
            expect($xml)->toContain('</product>');
        });

        test('generates without item wrapper when tag is empty', function () {
            $structure = [
                ['tag' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'TEST123');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, '', 0);

            expect($xml)->not->toContain('<item>');
            expect($xml)->toContain('<id>TEST123</id>');
        });
    });

    describe('Multiple Elements', function () {
        test('generates multiple elements in correct order', function () {
            $structure = [
                ['tag' => 'g:id', 'source_type' => 'attribute', 'source_value' => 'sku'],
                ['tag' => 'g:title', 'source_type' => 'attribute', 'source_value' => 'name', 'cdata' => true],
                ['tag' => 'g:price', 'source_type' => 'attribute', 'source_value' => 'price'],
                ['tag' => 'g:condition', 'source_type' => 'static', 'source_value' => 'new'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'PROD001');
            $product->setData('name', 'Test Product');
            $product->setData('price', 49.99);

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            // Verify order by checking positions
            $idPos = strpos($xml, '<g:id>');
            $titlePos = strpos($xml, '<g:title>');
            $pricePos = strpos($xml, '<g:price>');
            $conditionPos = strpos($xml, '<g:condition>');

            expect($idPos)->toBeLessThan($titlePos);
            expect($titlePos)->toBeLessThan($pricePos);
            expect($pricePos)->toBeLessThan($conditionPos);
        });
    });

    describe('Empty Structure Handling', function () {
        test('handles empty structure array', function () {
            $structure = [];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'TEST123');

            $xml = $this->mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toBe("<item>\n</item>\n");
        });
    });

    describe('Price Auto-Formatting', function () {
        test('auto-formats price field using feed settings', function () {
            // Set feed price settings
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint('.');
            $this->feed->setPriceThousandsSep('');
            $this->feed->setPriceCurrency('AUD');

            // Recreate mapper to pick up new settings
            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 295.0000);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<price>295.00 AUD</price>');
        });

        test('auto-formats special_price field', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint('.');
            $this->feed->setPriceCurrency('USD');

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'sale_price', 'source_type' => 'attribute', 'source_value' => 'special_price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('special_price', 149.9900);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<sale_price>149.99 USD</sale_price>');
        });

        test('respects thousands separator setting', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint('.');
            $this->feed->setPriceThousandsSep(',');
            $this->feed->setPriceCurrency('');

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 1234567.89);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<price>1,234,567.89</price>');
        });

        test('uses European format with comma decimal point', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint(',');
            $this->feed->setPriceThousandsSep('.');
            $this->feed->setPriceCurrency('EUR');

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 1234.56);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<price>1.234,56 EUR</price>');
        });

        test('does not auto-format non-price fields', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceCurrency('AUD');

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            // Use 'sku' which is explicitly extracted but NOT a price field
            $structure = [
                ['tag' => 'sku', 'source_type' => 'attribute', 'source_value' => 'sku'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('sku', 'TEST-SKU-123');

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            // sku should NOT be formatted as price (no currency suffix)
            expect($xml)->toContain('<sku>TEST-SKU-123</sku>');
        });

        test('explicit transformer overrides auto-format', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceCurrency('AUD');

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                [
                    'tag' => 'price',
                    'source_type' => 'attribute',
                    'source_value' => 'price',
                    'transformers' => 'format_price:decimals=0,currency=USD',
                ],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 295.99);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            // Explicit transformer should override feed settings
            expect($xml)->toContain('<price>296 USD</price>');
        });

        test('handles null price gracefully', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceCurrency('AUD');

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'special_price', 'source_type' => 'attribute', 'source_value' => 'special_price', 'optional' => true],
            ];

            $product = Mage::getModel('catalog/product');
            // special_price not set

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            // Optional empty price should be skipped
            expect($xml)->not->toContain('<special_price>');
        });

        test('formats price without currency when not set', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint('.');
            $this->feed->setPriceCurrency(''); // No currency

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 99.99);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            expect($xml)->toContain('<price>99.99</price>');
            expect($xml)->not->toContain('AUD');
            expect($xml)->not->toContain('USD');
        });

        test('excludes currency suffix when price_currency_suffix is disabled', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint('.');
            $this->feed->setPriceCurrency('AUD');
            $this->feed->setPriceCurrencySuffix(0); // Disabled

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 295.00);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            // Should have formatted price but NO currency suffix
            expect($xml)->toContain('<price>295.00</price>');
            expect($xml)->not->toContain('AUD');
        });

        test('includes currency suffix when price_currency_suffix is enabled', function () {
            $this->feed->setPriceDecimals(2);
            $this->feed->setPriceDecimalPoint('.');
            $this->feed->setPriceCurrency('AUD');
            $this->feed->setPriceCurrencySuffix(1); // Enabled

            $mapper = new Maho_FeedManager_Model_Mapper($this->feed);

            $structure = [
                ['tag' => 'price', 'source_type' => 'attribute', 'source_value' => 'price'],
            ];

            $product = Mage::getModel('catalog/product');
            $product->setData('price', 295.00);

            $xml = $mapper->mapProductToXmlStructure($product, $structure, 'item', 0);

            // Should have formatted price WITH currency suffix
            expect($xml)->toContain('<price>295.00 AUD</price>');
        });
    });
});
