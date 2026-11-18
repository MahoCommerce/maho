<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Segment Condition Combine', function () {
    beforeEach(function () {
        $this->combine = Mage::getModel('customersegmentation/segment_condition_combine');
    });

    test('has correct new child select options', function () {
        $options = $this->combine->getNewChildSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should have conditions group
        $hasConditionsGroup = false;
        foreach ($options as $option) {
            if (isset($option['label']) && is_string($option['label'])
                && strpos($option['label'], 'Condition') !== false) {
                $hasConditionsGroup = true;
                break;
            }
        }
        expect($hasConditionsGroup)->toBe(true);
    });

    test('can generate SQL conditions for database queries', function () {
        $this->combine->setAggregator('all');
        $this->combine->setValue(1);

        // Test that we can generate SQL - this is the core business functionality
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $this->combine->getConditionsSql($adapter, 1);

        // Empty conditions should return false
        expect($sql)->toBe(false);
    });

    test('handles negation in SQL generation', function () {
        $this->combine->setAggregator('any');
        $this->combine->setValue(0); // Test negation

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $this->combine->getConditionsSql($adapter, 1);

        // Empty conditions with negation should still return false
        expect($sql)->toBe(false);

        // Test that aggregator can be set
        expect($this->combine->getAggregator())->toBe('any');
        expect($this->combine->getValue())->toBe(0);
    });

    test('validates a nested ALL combine inside an ANY combine', function () {
        // A new combine has no prefix, and addCondition() drops a child of a combine without a prefix
        $this->combine->setPrefix('conditions')->setAggregator('any')->setValue(1);

        $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $nestedCombine->setPrefix('conditions')->setAggregator('all')->setValue(1);
        $nestedCombine->addCondition(Mage::getModel('customersegmentation/segment_condition_customer_attributes')
            ->setAttribute('email')->setOperator('{}')->setValue('@example.com'));
        $nestedCombine->addCondition(Mage::getModel('customersegmentation/segment_condition_customer_attributes')
            ->setAttribute('firstname')->setOperator('==')->setValue('John'));
        $this->combine->addCondition($nestedCombine);
        $this->combine->addCondition(Mage::getModel('customersegmentation/segment_condition_customer_attributes')
            ->setAttribute('lastname')->setOperator('==')->setValue('Admin'));

        $matchesNested = new \Maho\DataObject(['email' => 'john@example.com', 'firstname' => 'John', 'lastname' => 'NotAdmin']);
        $matchesStandalone = new \Maho\DataObject(['email' => 'jane@different.com', 'firstname' => 'Jane', 'lastname' => 'Admin']);
        $matchesHalfOfNested = new \Maho\DataObject(['email' => 'jane@example.com', 'firstname' => 'Jane', 'lastname' => 'NotAdmin']);

        expect($this->combine->getConditions())->toHaveCount(2)
            ->and($this->combine->validate($matchesNested))->toBeTrue()
            ->and($this->combine->validate($matchesStandalone))->toBeTrue()
            ->and($this->combine->validate($matchesHalfOfNested))->toBeFalse();
    });

    test('dynamically loads all available condition types from registry', function () {
        $options = $this->combine->getNewChildSelectOptions();

        // Should have main categories
        $categories = [];
        foreach ($options as $option) {
            if (isset($option['label'])) {
                $categories[] = $option['label'];
            }
        }

        // Check for expected categories
        expect($categories)->toContain('Customer Personal Information');
        expect($categories)->toContain('Customer Address');
        expect($categories)->toContain('Order History');
        expect($categories)->toContain('Shopping Cart');
        expect($categories)->toContain('Cart Items');
        expect($categories)->toContain('Viewed Products');
        expect($categories)->toContain('Wishlist');
        expect($categories)->toContain('Customer Time-based');
        expect($categories)->toContain('Newsletter Subscription');
    });

    test('dynamically loads product attributes for cart items conditions', function () {
        $options = $this->combine->getNewChildSelectOptions();

        // Find cart items category
        $cartItemsOptions = null;
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label'] === 'Cart Items') {
                $cartItemsOptions = $option['value'];
                break;
            }
        }

        expect($cartItemsOptions)->not->toBeNull();
        expect($cartItemsOptions)->toBeArray();

        // Should have cart item attributes (these should always be available)
        $hasCartAttributes = false;
        foreach ($cartItemsOptions as $item) {
            if (isset($item['label']) && (
                $item['label'] === 'Quantity in Cart'
                || $item['label'] === 'Price'
                || $item['label'] === 'Product Type'
            )) {
                $hasCartAttributes = true;
                break;
            }
        }
        expect($hasCartAttributes)->toBe(true);

        // Should have cart-specific attributes
        $hasQtyCondition = false;
        foreach ($cartItemsOptions as $item) {
            if (isset($item['label']) && $item['label'] === 'Quantity in Cart') {
                $hasQtyCondition = true;
                break;
            }
        }
        expect($hasQtyCondition)->toBe(true);
    });

    test('handles complex SQL generation with nested conditions', function () {
        $this->combine->setAggregator('all')->setValue(1);

        // Create mock conditions that return SQL
        $condition1 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition1->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn('customer_email LIKE "%@test.com"');

        $condition2 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition2->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn('customer_group_id = 1');

        // Add nested combine
        $nestedCombine = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
        $nestedCombine->expects($this->once())
                      ->method('getConditionsSql')
                      ->willReturn('lifetime_orders > 5 OR lifetime_sales > 1000');

        // Mock the getConditions method to return our mock conditions
        $this->combine = $this->createPartialMock(
            Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class,
            ['getConditions'],
        );
        $this->combine->setAggregator('all')->setValue(1);
        $this->combine->expects($this->once())
                      ->method('getConditions')
                      ->willReturn([$condition1, $condition2, $nestedCombine]);

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $this->combine->getConditionsSql($adapter, 1);

        expect($sql)->toBeString();
        expect($sql)->toContain('customer_email LIKE "%@test.com"');
        expect($sql)->toContain('customer_group_id = 1');
        expect($sql)->toContain('lifetime_orders > 5 OR lifetime_sales > 1000');
        expect($sql)->toContain(' AND '); // ALL aggregator
    });

    test('handles SQL generation with OR aggregator', function () {
        $this->combine->setAggregator('any')->setValue(1);

        // Create mock conditions
        $condition1 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition1->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn('customer_group_id = 1');

        $condition2 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition2->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn('lifetime_orders > 10');

        // Mock getConditions method
        $this->combine = $this->createPartialMock(
            Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class,
            ['getConditions'],
        );
        $this->combine->setAggregator('any')->setValue(1);
        $this->combine->expects($this->once())
                      ->method('getConditions')
                      ->willReturn([$condition1, $condition2]);

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $this->combine->getConditionsSql($adapter, 1);

        expect($sql)->toBeString();
        expect($sql)->toContain('customer_group_id = 1');
        expect($sql)->toContain('lifetime_orders > 10');
        expect($sql)->toContain(' OR '); // ANY aggregator
    });

    test('handles SQL generation with negation', function () {
        $this->combine->setAggregator('all')->setValue(0); // Negation (FALSE)

        // Create mock conditions
        $condition1 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition1->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn('customer_group_id = 1');

        // Mock getConditions method
        $this->combine = $this->createPartialMock(
            Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class,
            ['getConditions'],
        );
        $this->combine->setAggregator('all')->setValue(0);
        $this->combine->expects($this->once())
                      ->method('getConditions')
                      ->willReturn([$condition1]);

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $this->combine->getConditionsSql($adapter, 1);

        expect($sql)->toBeString();
        expect($sql)->toContain('NOT (');
        expect($sql)->toContain('customer_group_id = 1');
        expect($sql)->toContain(')');
    });

    test('handles conditions that return false SQL', function () {
        $this->combine->setAggregator('all')->setValue(1);

        // Create mock conditions - some return false (no SQL)
        $condition1 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition1->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn(false); // No SQL

        $condition2 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition2->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn('customer_group_id = 1');

        $condition3 = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
        $condition3->expects($this->once())
                   ->method('getConditionsSql')
                   ->willReturn(''); // Empty SQL

        // Mock getConditions method
        $this->combine = $this->createPartialMock(
            Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class,
            ['getConditions'],
        );
        $this->combine->setAggregator('all')->setValue(1);
        $this->combine->expects($this->once())
                      ->method('getConditions')
                      ->willReturn([$condition1, $condition2, $condition3]);

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = $this->combine->getConditionsSql($adapter, 1);

        // Should only include the valid condition
        expect($sql)->toBeString();
        expect($sql)->toContain('customer_group_id = 1');
        expect($sql)->not->toContain('false');
        expect($sql)->not->toContain('empty');
    });

    test('correctly sorts conditions alphabetically', function () {
        $options = $this->combine->getNewChildSelectOptions();

        // Find cart items conditions
        $cartItemsOptions = null;
        foreach ($options as $option) {
            if (isset($option['label']) && $option['label'] === 'Cart Items') {
                $cartItemsOptions = $option['value'];
                break;
            }
        }

        expect($cartItemsOptions)->not->toBeNull();

        // Extract labels and check sorting
        $labels = array_column($cartItemsOptions, 'label');
        $sortedLabels = $labels;
        sort($sortedLabels);

        expect($labels)->toEqual($sortedLabels);
    });
});
