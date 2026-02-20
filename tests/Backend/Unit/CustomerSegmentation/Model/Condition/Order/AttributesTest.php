<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Order Attributes Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
    });

    test('can create new condition instance', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Order_Attributes::class);
        expect($this->condition->getType())->toBe('customersegmentation/segment_condition_order_attributes');
    });

    test('extends abstract condition', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
    });

    test('has correct new child select options', function () {
        $options = $this->condition->getNewChildSelectOptions();

        expect($options)->toHaveKey('value');
        expect($options['value'])->toBe('customersegmentation/segment_condition_order_attributes');
        expect($options)->toHaveKey('label');
        expect($options['label'])->toBeString();
    });

    test('can get order attribute options', function () {
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should include order attributes
        expect($options)->toHaveKey('status');
        expect($options)->toHaveKey('grand_total');
    });

    test('can handle numeric order attributes', function () {
        $this->condition->setAttribute('grand_total');
        expect($this->condition->getInputType())->toBe('numeric');

        $this->condition->setAttribute('total_qty');
        expect($this->condition->getInputType())->toBe('numeric');
    });

    test('can handle string order attributes', function () {
        $this->condition->setAttribute('status');
        expect($this->condition->getInputType())->toBe('select');

        $this->condition->setAttribute('increment_id');
        expect($this->condition->getInputType())->toBe('string');
    });

    test('can handle date order attributes', function () {
        $this->condition->setAttribute('created_at');
        expect($this->condition->getInputType())->toBe('date');

        $this->condition->setAttribute('updated_at');
        expect($this->condition->getInputType())->toBe('date');
    });

    test('validates order condition correctly', function () {
        $this->condition->setAttribute('grand_total');
        $this->condition->setOperator('>=');
        $this->condition->setValue('100');

        // Mock order data
        $orderData = new Varien_Object([
            'grand_total' => '150.00',
        ]);

        expect($this->condition->validate($orderData))->toBe(true);

        $orderData->setGrandTotal('50.00');
        expect($this->condition->validate($orderData))->toBe(false);
    });

    test('can handle order status conditions', function () {
        $this->condition->setAttribute('status');
        $this->condition->setOperator('==');
        $this->condition->setValue('complete');

        $orderData = new Varien_Object(['status' => 'complete']);
        expect($this->condition->validate($orderData))->toBe(true);

        $orderData->setStatus('pending');
        expect($this->condition->validate($orderData))->toBe(false);
    });

    test('can handle multiple value conditions', function () {
        $this->condition->setAttribute('status');
        $this->condition->setOperator('()');
        $this->condition->setValue('complete,processing,shipped');

        expect($this->condition->validate(new Varien_Object(['status' => 'complete'])))->toBe(true);
        expect($this->condition->validate(new Varien_Object(['status' => 'processing'])))->toBe(true);
        expect($this->condition->validate(new Varien_Object(['status' => 'cancelled'])))->toBe(false);
    });

    test('can get order status select options', function () {
        $this->condition->setAttribute('status');

        $options = $this->condition->getValueSelectOptions();
        expect($options)->toBeArray();

        if (count($options) > 0) {
            expect($options[0])->toHaveKey('value');
            expect($options[0])->toHaveKey('label');
        }
    });

    test('handles aggregated conditions correctly', function () {
        // Test conditions that require aggregation (like total orders count)
        $this->condition->setAttribute('orders_count');
        $this->condition->setOperator('>=');
        $this->condition->setValue('5');

        expect($this->condition->getAttribute())->toBe('orders_count');
        expect($this->condition->getOperator())->toBe('>=');
        expect($this->condition->getValue())->toBe('5');
    });

    test('can export condition as array', function () {
        $this->condition->setAttribute('grand_total');
        $this->condition->setOperator('>=');
        $this->condition->setValue('100.00');

        $array = $this->condition->asArray();

        expect($array)->toHaveKey('type');
        expect($array)->toHaveKey('attribute');
        expect($array)->toHaveKey('operator');
        expect($array)->toHaveKey('value');

        expect($array['type'])->toBe('customersegmentation/segment_condition_order_attributes');
        expect($array['attribute'])->toBe('grand_total');
        expect($array['operator'])->toBe('>=');
        expect($array['value'])->toBe('100.00');
    });

    test('can handle currency formatting for monetary attributes', function () {
        $this->condition->setAttribute('grand_total');

        // Should handle decimal values
        $this->condition->setValue('99.95');
        expect($this->condition->getValue())->toBe('99.95');

        // Should handle integer values
        $this->condition->setValue('100');
        expect($this->condition->getValue())->toBe('100');
    });
});
