<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Cart Items Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_cart_items');
    });

    test('can get cart item attribute options', function () {
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should include cart item attributes
        expect($options)->toHaveKey('qty');
        expect($options)->toHaveKey('price');
    });

    test('gives each cart item attribute its input type', function (string $attribute, string $inputType) {
        $this->condition->setAttribute($attribute);
        expect($this->condition->getInputType())->toBe($inputType);
    })->with([
        'SKU' => ['sku', 'string'],
        'product ID' => ['product_id', 'numeric'],
        'quantity' => ['qty', 'numeric'],
        'price' => ['price', 'numeric'],
        'row total' => ['row_total', 'numeric'],
    ]);

    test('can handle product attribute conditions', function () {
        $this->condition->setAttribute('name');
        $this->condition->setOperator('{}');  // contains
        $this->condition->setValue('Test');

        $cartItemData = new \Maho\DataObject(['name' => 'Test Product Name']);
        expect($this->condition->validate($cartItemData))->toBe(true);

        $cartItemData->setName('Different Product');
        expect($this->condition->validate($cartItemData))->toBe(false);
    });

    test('can get appropriate value element type', function () {
        // String attributes should use text
        $this->condition->setAttribute('sku');
        expect($this->condition->getValueElementType())->toBe('text');

        // Numeric attributes should use text (for number input)
        $this->condition->setAttribute('qty');
        expect($this->condition->getValueElementType())->toBe('text');

        // Category attributes might use multiselect
        $this->condition->setAttribute('category_ids');
        $elementType = $this->condition->getValueElementType();
        expect($elementType)->toBeIn(['text', 'multiselect']);
    });

    test('handles empty cart conditions gracefully', function () {
        $this->condition->setAttribute('qty');
        $this->condition->setOperator('>');
        $this->condition->setValue('0');

        // Empty cart item should not match quantity > 0
        $emptyCartData = new \Maho\DataObject([]);
        expect($this->condition->validate($emptyCartData))->toBe(false);
    });
});
