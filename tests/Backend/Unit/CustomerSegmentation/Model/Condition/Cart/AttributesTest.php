<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Cart Attributes Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_cart_attributes');
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
        expect($sql)->toContain('e.entity_id IN')
            ->and($sql)->toContain('q.is_active')
            ->and($sql)->toMatch("/q\\.items_count >= '?2'?/");

        // Test applied rules condition
        $this->condition->setAttribute('applied_rule_ids');
        $this->condition->setOperator('{}');
        $this->condition->setValue('123');

        $sql = $this->condition->getConditionsSql($adapter);
        expect($sql)->toContain('q.applied_rule_ids')
            ->and($sql)->toContain('%123%');
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
});
