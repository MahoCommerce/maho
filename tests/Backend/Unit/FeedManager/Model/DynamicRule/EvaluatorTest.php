<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('DynamicRule Evaluator', function () {
    beforeEach(function () {
        $this->rule = Mage::getModel('feedmanager/dynamicRule');
    });

    describe('Basic Operators', function () {
        test('eq operator matches equal values', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'status', 'operator' => 'eq', 'value' => '1']], 'output_type' => 'static', 'output_value' => 'enabled'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'disabled'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['status' => '1']))->toBe('enabled');
            expect($evaluator->evaluate(['status' => '0']))->toBe('disabled');
        });

        test('neq operator matches non-equal values', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'status', 'operator' => 'neq', 'value' => '0']], 'output_type' => 'static', 'output_value' => 'enabled'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'disabled'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['status' => '1']))->toBe('enabled');
            expect($evaluator->evaluate(['status' => '0']))->toBe('disabled');
        });

        test('gt operator compares numeric values', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'qty', 'operator' => 'gt', 'value' => '0']], 'output_type' => 'static', 'output_value' => 'in_stock'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'out_of_stock'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['qty' => 10]))->toBe('in_stock');
            expect($evaluator->evaluate(['qty' => 0]))->toBe('out_of_stock');
            expect($evaluator->evaluate(['qty' => -1]))->toBe('out_of_stock');
        });

        test('lt operator compares numeric values', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'qty', 'operator' => 'lt', 'value' => '5']], 'output_type' => 'static', 'output_value' => 'low_stock'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'in_stock'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['qty' => 3]))->toBe('low_stock');
            expect($evaluator->evaluate(['qty' => 5]))->toBe('in_stock');
            expect($evaluator->evaluate(['qty' => 10]))->toBe('in_stock');
        });

        test('gteq operator includes boundary', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'qty', 'operator' => 'gteq', 'value' => '5']], 'output_type' => 'static', 'output_value' => 'ok'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'low'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['qty' => 5]))->toBe('ok');
            expect($evaluator->evaluate(['qty' => 6]))->toBe('ok');
            expect($evaluator->evaluate(['qty' => 4]))->toBe('low');
        });

        test('lteq operator includes boundary', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'qty', 'operator' => 'lteq', 'value' => '5']], 'output_type' => 'static', 'output_value' => 'low'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'ok'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['qty' => 5]))->toBe('low');
            expect($evaluator->evaluate(['qty' => 4]))->toBe('low');
            expect($evaluator->evaluate(['qty' => 6]))->toBe('ok');
        });
    });

    describe('List Operators', function () {
        test('in operator matches values in list', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'type_id', 'operator' => 'in', 'value' => 'simple,virtual']], 'output_type' => 'static', 'output_value' => 'basic'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'complex'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['type_id' => 'simple']))->toBe('basic');
            expect($evaluator->evaluate(['type_id' => 'virtual']))->toBe('basic');
            expect($evaluator->evaluate(['type_id' => 'configurable']))->toBe('complex');
        });

        test('nin operator excludes values in list', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'type_id', 'operator' => 'nin', 'value' => 'configurable,bundle']], 'output_type' => 'static', 'output_value' => 'simple'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'complex'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['type_id' => 'simple']))->toBe('simple');
            expect($evaluator->evaluate(['type_id' => 'configurable']))->toBe('complex');
        });
    });

    describe('String Operators', function () {
        test('like operator performs case-insensitive contains', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'name', 'operator' => 'like', 'value' => 'tennis']], 'output_type' => 'static', 'output_value' => 'sports'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'other'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['name' => 'Tennis Racket']))->toBe('sports');
            expect($evaluator->evaluate(['name' => 'TENNIS BALLS']))->toBe('sports');
            expect($evaluator->evaluate(['name' => 'Golf Club']))->toBe('other');
        });

        test('nlike operator excludes containing strings', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'sku', 'operator' => 'nlike', 'value' => 'SAMPLE']], 'output_type' => 'static', 'output_value' => 'real'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'sample'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['sku' => 'ABC123']))->toBe('real');
            expect($evaluator->evaluate(['sku' => 'SAMPLE-001']))->toBe('sample');
        });
    });

    describe('Null Operators', function () {
        test('null operator matches empty values', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'gtin', 'operator' => 'null', 'value' => '']], 'output_type' => 'static', 'output_value' => 'no'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'yes'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['gtin' => '']))->toBe('no');
            expect($evaluator->evaluate(['gtin' => null]))->toBe('no');
            expect($evaluator->evaluate([]))->toBe('no'); // Missing attribute
            expect($evaluator->evaluate(['gtin' => '123456789']))->toBe('yes');
        });

        test('notnull operator matches non-empty values', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'gtin', 'operator' => 'notnull', 'value' => '']], 'output_type' => 'static', 'output_value' => 'yes'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'no'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['gtin' => '123456789']))->toBe('yes');
            expect($evaluator->evaluate(['gtin' => '']))->toBe('no');
            expect($evaluator->evaluate(['gtin' => null]))->toBe('no');
        });
    });

    describe('Attribute Comparison Operators', function () {
        test('lt_attr compares attribute to another attribute', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'special_price', 'operator' => 'lt_attr', 'value' => 'price']], 'output_type' => 'static', 'output_value' => 'on_sale'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'regular'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['special_price' => 80, 'price' => 100]))->toBe('on_sale');
            expect($evaluator->evaluate(['special_price' => 100, 'price' => 100]))->toBe('regular');
            expect($evaluator->evaluate(['special_price' => 120, 'price' => 100]))->toBe('regular');
        });

        test('gt_attr compares attribute greater than another', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'cost', 'operator' => 'gt_attr', 'value' => 'price']], 'output_type' => 'static', 'output_value' => 'loss'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'profit'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['cost' => 120, 'price' => 100]))->toBe('loss');
            expect($evaluator->evaluate(['cost' => 50, 'price' => 100]))->toBe('profit');
        });
    });

    describe('AND Logic (Multiple Conditions)', function () {
        test('all conditions must match within a row', function () {
            $this->rule->setOutputRows([
                [
                    'conditions' => [
                        ['attribute' => 'is_in_stock', 'operator' => 'eq', 'value' => '1'],
                        ['attribute' => 'qty', 'operator' => 'gt', 'value' => '0'],
                    ],
                    'output_type' => 'static',
                    'output_value' => 'available',
                ],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'unavailable'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['is_in_stock' => '1', 'qty' => 10]))->toBe('available');
            expect($evaluator->evaluate(['is_in_stock' => '1', 'qty' => 0]))->toBe('unavailable');
            expect($evaluator->evaluate(['is_in_stock' => '0', 'qty' => 10]))->toBe('unavailable');
        });
    });

    describe('OR Logic (Multiple Rows)', function () {
        test('first matching row wins', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'qty', 'operator' => 'gt', 'value' => '10']], 'output_type' => 'static', 'output_value' => 'high'],
                ['conditions' => [['attribute' => 'qty', 'operator' => 'gt', 'value' => '0']], 'output_type' => 'static', 'output_value' => 'low'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'none'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['qty' => 20]))->toBe('high');
            expect($evaluator->evaluate(['qty' => 5]))->toBe('low');
            expect($evaluator->evaluate(['qty' => 0]))->toBe('none');
        });
    });

    describe('Output Types', function () {
        test('static output returns fixed value', function () {
            $this->rule->setOutputRows([
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'fixed_value'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);
            expect($evaluator->evaluate(['any' => 'data']))->toBe('fixed_value');
        });

        test('attribute output returns product attribute', function () {
            $this->rule->setOutputRows([
                ['conditions' => [], 'output_type' => 'attribute', 'output_attribute' => 'name'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);
            expect($evaluator->evaluate(['name' => 'Test Product']))->toBe('Test Product');
        });

        test('combined output prepends value to attribute', function () {
            $this->rule->setOutputRows([
                ['conditions' => [], 'output_type' => 'combined', 'output_value' => 'https://example.com/media/', 'output_attribute' => 'image'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);
            expect($evaluator->evaluate(['image' => 'product.jpg']))->toBe('https://example.com/media/product.jpg');
        });
    });

    describe('Real-World Scenarios', function () {
        test('availability rule with multiple stock levels', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'qty', 'operator' => 'gt', 'value' => '1']], 'output_type' => 'static', 'output_value' => 'in_stock'],
                ['conditions' => [['attribute' => 'qty', 'operator' => 'eq', 'value' => '1']], 'output_type' => 'static', 'output_value' => 'limited_availability'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'out_of_stock'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['qty' => 10]))->toBe('in_stock');
            expect($evaluator->evaluate(['qty' => 1]))->toBe('limited_availability');
            expect($evaluator->evaluate(['qty' => 0]))->toBe('out_of_stock');
        });

        test('identifier_exists rule', function () {
            $this->rule->setOutputRows([
                ['conditions' => [['attribute' => 'gtin', 'operator' => 'notnull', 'value' => '']], 'output_type' => 'static', 'output_value' => 'yes'],
                ['conditions' => [['attribute' => 'mpn', 'operator' => 'notnull', 'value' => '']], 'output_type' => 'static', 'output_value' => 'yes'],
                ['conditions' => [], 'output_type' => 'static', 'output_value' => 'no'],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['gtin' => '123456789', 'mpn' => '']))->toBe('yes');
            expect($evaluator->evaluate(['gtin' => '', 'mpn' => 'ABC123']))->toBe('yes');
            expect($evaluator->evaluate(['gtin' => '', 'mpn' => '']))->toBe('no');
        });

        test('image url rule with fallback', function () {
            $this->rule->setOutputRows([
                [
                    'conditions' => [['attribute' => 'facebook_image', 'operator' => 'notnull', 'value' => '']],
                    'output_type' => 'combined',
                    'output_value' => 'https://example.com/media/',
                    'output_attribute' => 'facebook_image',
                ],
                [
                    'conditions' => [],
                    'output_type' => 'combined',
                    'output_value' => 'https://example.com/media/',
                    'output_attribute' => 'base_image',
                ],
            ]);

            $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($this->rule);

            expect($evaluator->evaluate(['facebook_image' => 'fb.jpg', 'base_image' => 'base.jpg']))
                ->toBe('https://example.com/media/fb.jpg');

            expect($evaluator->evaluate(['facebook_image' => '', 'base_image' => 'base.jpg']))
                ->toBe('https://example.com/media/base.jpg');
        });
    });
});
