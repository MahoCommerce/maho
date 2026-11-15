<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Attributes Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
    });

    test('has correct new child select options structure', function () {
        $options = $this->condition->getNewChildSelectOptions();

        expect($options)->toHaveKey('value');
        expect($options['value'])->toBe('customersegmentation/segment_condition_customer_attributes');
        expect($options)->toHaveKey('label');
        expect($options['label'])->toBeString();
    });

    test('can get attribute options', function () {
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should include basic customer attributes
        expect($options)->toHaveKey('email');
        expect($options)->toHaveKey('firstname');
        expect($options)->toHaveKey('lastname');
    });

    test('can handle different operators', function () {
        $this->condition->setAttribute('entity_id');
        $this->condition->setValue('100');

        // Test equals
        $this->condition->setOperator('==');
        expect($this->condition->validate(new Varien_Object(['entity_id' => '100'])))->toBe(true);
        expect($this->condition->validate(new Varien_Object(['entity_id' => '101'])))->toBe(false);

        // Test not equals
        $this->condition->setOperator('!=');
        expect($this->condition->validate(new Varien_Object(['entity_id' => '100'])))->toBe(false);
        expect($this->condition->validate(new Varien_Object(['entity_id' => '101'])))->toBe(true);
    });

    test('offers the options of the gender attribute', function () {
        $this->condition->setAttribute('gender');

        $labels = array_column($this->condition->getValueSelectOptions(), 'label');

        expect($labels)->toContain('Male', 'Female');
    });
});
