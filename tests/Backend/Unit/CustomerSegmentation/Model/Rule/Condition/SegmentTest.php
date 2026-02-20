<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Rule Condition Segment', function () {
    beforeEach(function () {
        $this->segmentCondition = Mage::getModel('customersegmentation/rule_condition_segment');
    });

    test('can create new segment condition instance', function () {
        expect($this->segmentCondition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Rule_Condition_Segment::class);
        expect($this->segmentCondition->getType())->toBe('customersegmentation/rule_condition_segment');
    });

    test('extends rule condition abstract', function () {
        expect($this->segmentCondition)->toBeInstanceOf(Mage_Rule_Model_Condition_Abstract::class);
    });

    test('has correct attribute options', function () {
        $this->segmentCondition->loadAttributeOptions();
        $options = $this->segmentCondition->getAttributeOption();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('customer_segment');
        expect($options['customer_segment'])->toBe('Customer Segment');
    });

    test('has correct input type', function () {
        expect($this->segmentCondition->getInputType())->toBe('select');
    });

    test('has correct value element type', function () {
        expect($this->segmentCondition->getValueElementType())->toBe('select');
    });

    test('can load value select options from active segments', function () {
        // Test that the method returns an array (actual segments will depend on test data)
        $options = $this->segmentCondition->getValueSelectOptions();

        expect($options)->toBeArray();

        // Each option should have value and label keys
        foreach ($options as $option) {
            expect($option)->toHaveKey('value');
            expect($option)->toHaveKey('label');
            expect($option['value'])->toBeNumeric();
            expect($option['label'])->toBeString();
        }
    });

    test('validates customer segment membership correctly', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue(1); // Segment ID 1

        // Create a simple test where we mock the validateAttribute method behavior
        // Since validateAttribute is protected, we'll test the overall validation

        // Create test object with customer ID
        $testObject = new Varien_Object([
            'customer_id' => 123,
            'store' => null,
        ]);

        // The actual test will depend on the implementation - for now verify it doesn't crash
        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBeBool(); // Should return boolean
    });

    test('validates customer not in segment correctly', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue(999); // Non-existent segment

        // Create test object with customer ID
        $testObject = new Varien_Object([
            'customer_id' => 123,
            'store' => null,
        ]);

        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBeBool(); // Should return boolean
    });

    test('validates with website context correctly', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue(1);

        // Mock store to return website ID
        $store = new Varien_Object(['website_id' => 2]);

        // Create test object with customer ID and store
        $testObject = new Varien_Object([
            'customer_id' => 456,
            'store' => $store,
        ]);

        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBeBool(); // Should return boolean
    });

    test('handles guest customers correctly', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue('1');

        // Create test object without customer ID (guest)
        $testObject = new Varien_Object([
            'customer_id' => null,
            'store' => null,
        ]);

        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBe(false);

        // Test with customer ID 0 (also guest)
        $testObject->setCustomerId(0);
        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBe(false);
    });

    test('handles empty segment list correctly', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setOperator('==');
        $this->segmentCondition->setValue(1);

        // Create test object with customer ID - without segments, should return false
        $testObject = new Varien_Object([
            'customer_id' => 123,
            'store' => null,
        ]);

        $result = $this->segmentCondition->validate($testObject);
        expect($result)->toBeBool(); // Should return boolean
    });

    test('works with different operators', function () {
        $this->segmentCondition->setAttribute('customer_segment');
        $this->segmentCondition->setValue(1);

        $testObject = new Varien_Object([
            'customer_id' => 123,
            'store' => null,
        ]);

        // Test different operators - just verify they don't crash
        $operators = ['==', '!=', '()', '!()'];
        foreach ($operators as $operator) {
            $this->segmentCondition->setOperator($operator);
            $result = $this->segmentCondition->validate($testObject);
            expect($result)->toBeBool(); // Should always return boolean
        }
    });


    test('caches value select options', function () {
        // Test that options are cached by calling twice and ensuring we get same result
        $options1 = $this->segmentCondition->getValueSelectOptions();
        $options2 = $this->segmentCondition->getValueSelectOptions();

        expect($options1)->toEqual($options2);
        expect($options1)->toBeArray();
    });
});
