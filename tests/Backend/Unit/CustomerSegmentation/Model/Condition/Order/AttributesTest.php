<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Order Attributes Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
    });

    test('can get order attribute options', function () {
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should include order attributes
        expect($options)->toHaveKey('status');
        expect($options)->toHaveKey('grand_total');
    });

    test('gives each order attribute its input type', function (string $attribute, string $inputType) {
        $this->condition->setAttribute($attribute);
        expect($this->condition->getInputType())->toBe($inputType);
    })->with([
        'grand total' => ['grand_total', 'numeric'],
        'total quantity' => ['total_qty', 'numeric'],
        'status' => ['status', 'select'],
        'increment ID' => ['increment_id', 'string'],
        'creation date' => ['created_at', 'date'],
        'update date' => ['updated_at', 'date'],
    ]);

    test('can handle order status conditions', function () {
        $this->condition->setAttribute('status');
        $this->condition->setOperator('==');
        $this->condition->setValue('complete');

        $orderData = new \Maho\DataObject(['status' => 'complete']);
        expect($this->condition->validate($orderData))->toBe(true);

        $orderData->setStatus('pending');
        expect($this->condition->validate($orderData))->toBe(false);
    });

    test('can handle multiple value conditions', function () {
        $this->condition->setAttribute('status');
        $this->condition->setOperator('()');
        $this->condition->setValue('complete,processing,shipped');

        expect($this->condition->validate(new \Maho\DataObject(['status' => 'complete'])))->toBe(true);
        expect($this->condition->validate(new \Maho\DataObject(['status' => 'processing'])))->toBe(true);
        expect($this->condition->validate(new \Maho\DataObject(['status' => 'cancelled'])))->toBe(false);
    });
});
