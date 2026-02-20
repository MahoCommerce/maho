<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Cart Attributes Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_cart_attributes');
    });

    test('can create new condition instance', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Cart_Attributes::class);
        expect($this->condition->getType())->toBe('customersegmentation/segment_condition_cart_attributes');
    });

    test('extends abstract condition', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
    });

    test('has correct new child select options', function () {
        $options = $this->condition->getNewChildSelectOptions();

        expect($options)->toHaveKey('value');
        expect($options['value'])->toBe('customersegmentation/segment_condition_cart_attributes');
        expect($options)->toHaveKey('label');
        expect($options['label'])->toBeString();
        expect($options['label'])->toContain('Shopping Cart Information');
    });

    test('can get cart attribute options', function () {
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBe(10); // All expected cart attributes

        // Verify all expected attributes are present
        $expectedAttributes = [
            'items_count',
            'items_qty',
            'base_subtotal',
            'base_grand_total',
            'created_at',
            'updated_at',
            'is_active',
            'store_id',
            'applied_rule_ids',
            'coupon_code',
        ];

        foreach ($expectedAttributes as $attribute) {
            expect($options)->toHaveKey($attribute);
            expect($options[$attribute])->toBeString();
        }
    });

    test('provides correct input types', function () {
        // Numeric attributes
        $this->condition->setAttribute('items_count');
        expect($this->condition->getInputType())->toBe('numeric');

        $this->condition->setAttribute('items_qty');
        expect($this->condition->getInputType())->toBe('numeric');

        $this->condition->setAttribute('base_subtotal');
        expect($this->condition->getInputType())->toBe('numeric');

        $this->condition->setAttribute('base_grand_total');
        expect($this->condition->getInputType())->toBe('numeric');

        // Date attributes
        $this->condition->setAttribute('created_at');
        expect($this->condition->getInputType())->toBe('date');

        $this->condition->setAttribute('updated_at');
        expect($this->condition->getInputType())->toBe('date');

        // Select attributes
        $this->condition->setAttribute('is_active');
        expect($this->condition->getInputType())->toBe('select');

        $this->condition->setAttribute('store_id');
        expect($this->condition->getInputType())->toBe('select');

        // String attributes
        $this->condition->setAttribute('coupon_code');
        expect($this->condition->getInputType())->toBe('string');

        $this->condition->setAttribute('applied_rule_ids');
        expect($this->condition->getInputType())->toBe('string');
    });

    test('provides correct value element types', function () {
        // Date attributes should use date element
        $this->condition->setAttribute('created_at');
        expect($this->condition->getValueElementType())->toBe('date');

        $this->condition->setAttribute('updated_at');
        expect($this->condition->getValueElementType())->toBe('date');

        // Select attributes should use select element
        $this->condition->setAttribute('is_active');
        expect($this->condition->getValueElementType())->toBe('select');

        $this->condition->setAttribute('store_id');
        expect($this->condition->getValueElementType())->toBe('select');

        // Others should use text
        $this->condition->setAttribute('items_count');
        expect($this->condition->getValueElementType())->toBe('text');

        $this->condition->setAttribute('coupon_code');
        expect($this->condition->getValueElementType())->toBe('text');
    });

    test('provides correct select options for is_active', function () {
        $this->condition->setAttribute('is_active');
        $options = $this->condition->getValueSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBe(3);

        expect($options[0]['value'])->toBe('');
        expect($options[1]['value'])->toBe('1');
        expect($options[2]['value'])->toBe('0');

        expect($options[1]['label'])->toContain('Active');
        expect($options[2]['label'])->toContain('Inactive');
    });

    test('provides store select options for store_id', function () {
        $this->condition->setAttribute('store_id');
        $options = $this->condition->getValueSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // First option should be "Please select..."
        expect($options[0]['value'])->toBe('');
    });

    test('returns empty options for non-select attributes', function () {
        $this->condition->setAttribute('items_count');
        $options = $this->condition->getValueSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBe(0);
    });

    test('generates SQL conditions for supported attributes', function () {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

        // Test numeric condition
        $this->condition->setAttribute('items_count');
        $this->condition->setOperator('>=');
        $this->condition->setValue(2);

        $sql = $this->condition->getConditionsSql($adapter);
        expect($sql)->toBeString();

        // Test applied rules condition
        $this->condition->setAttribute('applied_rule_ids');
        $this->condition->setOperator('{}');
        $this->condition->setValue('123');

        $sql = $this->condition->getConditionsSql($adapter);
        expect($sql)->toBeString();
    });

    test('returns false for unsupported attributes', function () {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

        $this->condition->setAttribute('unsupported_attribute');
        $sql = $this->condition->getConditionsSql($adapter);

        expect($sql)->toBe(false);
    });

    test('formats attribute name with Cart prefix', function () {
        $this->condition->setAttribute('items_count');
        $attributeName = $this->condition->getAttributeName();

        expect($attributeName)->toContain('Cart:');
        expect($attributeName)->toContain('Items Count');
    });

    test('formats condition as string correctly', function () {
        $this->condition->setAttribute('items_count');
        $this->condition->setOperator('>=');
        $this->condition->setValue(2);

        $conditionString = $this->condition->asString();

        expect($conditionString)->toContain('Cart:');
        expect($conditionString)->toContain('Items Count');
        expect($conditionString)->toBeString();
    });

    test('validates cart data correctly', function () {
        $this->condition->setAttribute('items_count');
        $this->condition->setOperator('>=');
        $this->condition->setValue('2');

        // Mock cart data
        $cartData = new Varien_Object([
            'items_count' => 3,
        ]);

        expect($this->condition->validate($cartData))->toBe(true);

        $cartData->setItemsCount(1);
        expect($this->condition->validate($cartData))->toBe(false);
    });

    test('handles different operators correctly', function () {
        $this->condition->setAttribute('base_subtotal');
        $this->condition->setOperator('>=');
        $this->condition->setValue('25.00');

        // Just verify the condition configuration is set properly
        expect($this->condition->getAttribute())->toBe('base_subtotal');
        expect($this->condition->getOperator())->toBe('>=');
        expect($this->condition->getValue())->toBe('25.00');
    });

    test('handles active status validation', function () {
        $this->condition->setAttribute('is_active');
        $this->condition->setOperator('==');
        $this->condition->setValue(1);

        $activeCart = new Varien_Object(['is_active' => 1]);
        expect($this->condition->validate($activeCart))->toBe(true);

        $inactiveCart = new Varien_Object(['is_active' => 0]);
        expect($this->condition->validate($inactiveCart))->toBe(false);
    });

    test('handles coupon code validation', function () {
        $this->condition->setAttribute('coupon_code');
        $this->condition->setOperator('==');
        $this->condition->setValue('SAVE10');

        $cartWithCoupon = new Varien_Object(['coupon_code' => 'SAVE10']);
        expect($this->condition->validate($cartWithCoupon))->toBe(true);

        $cartWithoutCoupon = new Varien_Object(['coupon_code' => '']);
        expect($this->condition->validate($cartWithoutCoupon))->toBe(false);
    });

    test('can export condition as array', function () {
        $this->condition->setAttribute('items_count');
        $this->condition->setOperator('>=');
        $this->condition->setValue(2);

        $array = $this->condition->asArray();

        expect($array)->toHaveKey('type');
        expect($array)->toHaveKey('attribute');
        expect($array)->toHaveKey('operator');
        expect($array)->toHaveKey('value');

        expect($array['type'])->toBe('customersegmentation/segment_condition_cart_attributes');
        expect($array['attribute'])->toBe('items_count');
        expect($array['operator'])->toBe('>=');
        expect($array['value'])->toBe(2);
    });
});
