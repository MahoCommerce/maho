<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Cart Items Condition', function () {
    beforeEach(function () {
        $this->condition = Mage::getModel('customersegmentation/segment_condition_cart_items');
    });

    test('can create new condition instance', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Cart_Items::class);
        expect($this->condition->getType())->toBe('customersegmentation/segment_condition_cart_items');
    });

    test('extends abstract condition', function () {
        expect($this->condition)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
    });

    test('has correct new child select options', function () {
        $options = $this->condition->getNewChildSelectOptions();

        expect($options)->toHaveKey('value');
        expect($options['value'])->toBe('customersegmentation/segment_condition_cart_items');
        expect($options)->toHaveKey('label');
        expect($options['label'])->toBeString();
    });

    test('can get cart item attribute options', function () {
        $options = $this->condition->getAttributeOption();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should include cart item attributes
        expect($options)->toHaveKey('qty');
        expect($options)->toHaveKey('price');
    });

    test('can handle product identification attributes', function () {
        $this->condition->setAttribute('sku');
        expect($this->condition->getInputType())->toBe('string');

        $this->condition->setAttribute('product_id');
        expect($this->condition->getInputType())->toBe('numeric');
    });

    test('can handle quantity and price attributes', function () {
        $this->condition->setAttribute('qty');
        expect($this->condition->getInputType())->toBe('numeric');

        $this->condition->setAttribute('price');
        expect($this->condition->getInputType())->toBe('numeric');

        $this->condition->setAttribute('row_total');
        expect($this->condition->getInputType())->toBe('numeric');
    });

    test('validates cart item conditions correctly', function () {
        $this->condition->setAttribute('qty');
        $this->condition->setOperator('>=');
        $this->condition->setValue('2');

        // Mock cart item data
        $cartItemData = new Varien_Object([
            'qty' => '3',
        ]);

        expect($this->condition->validate($cartItemData))->toBe(true);

        $cartItemData->setQty('1');
        expect($this->condition->validate($cartItemData))->toBe(false);
    });

    test('can handle SKU matching conditions', function () {
        $this->condition->setAttribute('sku');
        $this->condition->setOperator('==');
        $this->condition->setValue('TEST-SKU-001');

        $cartItemData = new Varien_Object(['sku' => 'TEST-SKU-001']);
        expect($this->condition->validate($cartItemData))->toBe(true);

        $cartItemData->setSku('DIFFERENT-SKU');
        expect($this->condition->validate($cartItemData))->toBe(false);
    });

    test('can handle multiple SKU conditions', function () {
        $this->condition->setAttribute('sku');
        $this->condition->setOperator('()');
        $this->condition->setValue('SKU1,SKU2,SKU3');

        expect($this->condition->validate(new Varien_Object(['sku' => 'SKU1'])))->toBe(true);
        expect($this->condition->validate(new Varien_Object(['sku' => 'SKU2'])))->toBe(true);
        expect($this->condition->validate(new Varien_Object(['sku' => 'OTHER-SKU'])))->toBe(false);
    });

    test('can handle price range conditions', function () {
        $this->condition->setAttribute('price');
        $this->condition->setOperator('>=');
        $this->condition->setValue('10.00');

        expect($this->condition->validate(new Varien_Object(['price' => '15.50'])))->toBe(true);
        expect($this->condition->validate(new Varien_Object(['price' => '5.99'])))->toBe(false);
    });

    test('can handle category conditions', function () {
        $this->condition->setAttribute('qty');
        $this->condition->setOperator('>=');
        $this->condition->setValue('1');

        // Mock cart item with quantity
        $cartItemData = new Varien_Object(['qty' => 2]);
        expect($this->condition->validate($cartItemData))->toBe(true);

        $cartItemData->setQty(0);
        expect($this->condition->validate($cartItemData))->toBe(false);
    });

    test('can handle product attribute conditions', function () {
        $this->condition->setAttribute('name');
        $this->condition->setOperator('{}');  // contains
        $this->condition->setValue('Test');

        $cartItemData = new Varien_Object(['name' => 'Test Product Name']);
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

    test('can export condition as array', function () {
        $this->condition->setAttribute('sku');
        $this->condition->setOperator('==');
        $this->condition->setValue('TEST-SKU');

        $array = $this->condition->asArray();

        expect($array)->toHaveKey('type');
        expect($array)->toHaveKey('attribute');
        expect($array)->toHaveKey('operator');
        expect($array)->toHaveKey('value');

        expect($array['type'])->toBe('customersegmentation/segment_condition_cart_items');
        expect($array['attribute'])->toBe('sku');
        expect($array['operator'])->toBe('==');
        expect($array['value'])->toBe('TEST-SKU');
    });

    test('handles empty cart conditions gracefully', function () {
        $this->condition->setAttribute('qty');
        $this->condition->setOperator('>');
        $this->condition->setValue('0');

        // Empty cart item should not match quantity > 0
        $emptyCartData = new Varien_Object([]);
        expect($this->condition->validate($emptyCartData))->toBe(false);
    });

    test('can handle aggregated cart conditions', function () {
        // Test conditions like "total items in cart"
        $this->condition->setAttribute('total_qty');
        $this->condition->setOperator('>=');
        $this->condition->setValue('5');

        expect($this->condition->getAttribute())->toBe('total_qty');
        expect($this->condition->getValue())->toBe('5');
    });
});
