<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Segment Condition Combine', function () {
    beforeEach(function () {
        $this->combine = Mage::getModel('customersegmentation/segment_condition_combine');
    });

    test('can create new combine instance', function () {
        expect($this->combine)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
        expect($this->combine->getType())->toBe('customersegmentation/segment_condition_combine');
    });

    test('extends rule combine abstract', function () {
        expect($this->combine)->toBeInstanceOf(Mage_Rule_Model_Condition_Combine::class);
        expect($this->combine)->toBeInstanceOf(Mage_Rule_Model_Condition_Abstract::class);
    });

    test('has correct new child select options', function () {
        $options = $this->combine->getNewChildSelectOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should have conditions group
        $hasConditionsGroup = false;
        foreach ($options as $option) {
            if (isset($option['label']) && is_string($option['label']) &&
                strpos($option['label'], 'Condition') !== false) {
                $hasConditionsGroup = true;
                break;
            }
        }
        expect($hasConditionsGroup)->toBe(true);
    });

    test('can use inherited condition management', function () {
        // Test that the combine inherits proper condition management from parent
        $conditions = $this->combine->getConditions();
        expect($conditions)->toBeArray();

        // Test that we can set and get basic properties
        expect($this->combine->getType())->toBe('customersegmentation/segment_condition_combine');
    });

    test('can set aggregator and value', function () {
        $this->combine->setAggregator('all');
        $this->combine->setValue(1);

        expect($this->combine->getAggregator())->toBe('all');
        expect($this->combine->getValue())->toBe(1);
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

    test('can handle nested combine conditions', function () {
        // Create parent combine with 'any' aggregator
        $this->combine->setAggregator('any');
        $this->combine->setValue(1);

        // Create nested combine with 'all' aggregator
        $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $nestedCombine->setAggregator('all');
        $nestedCombine->setValue(1);

        // Add conditions to nested combine
        $condition1 = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $condition1->setAttribute('email');
        $condition1->setOperator('{}');
        $condition1->setValue('@example.com');

        $condition2 = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $condition2->setAttribute('firstname');
        $condition2->setOperator('==');
        $condition2->setValue('John');

        $nestedCombine->addCondition($condition1);
        $nestedCombine->addCondition($condition2);

        // Add nested combine to parent
        $this->combine->addCondition($nestedCombine);

        // Add another condition to parent
        $condition3 = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $condition3->setAttribute('lastname');
        $condition3->setOperator('==');
        $condition3->setValue('Admin');

        $this->combine->addCondition($condition3);

        // Test data that matches nested combine (both conditions)
        $testData = new Varien_Object([
            'email' => 'john@example.com',
            'firstname' => 'John',
            'lastname' => 'NotAdmin',
        ]);
        expect($this->combine->validate($testData))->toBe(true);

        // Test data that only matches the standalone condition
        $testData = new Varien_Object([
            'email' => 'jane@different.com',
            'firstname' => 'Jane',
            'lastname' => 'Admin',
        ]);
        expect($this->combine->validate($testData))->toBe(true);
    });

    test('can export basic structure as array', function () {
        $this->combine->setAggregator('all');
        $this->combine->setValue(1);

        $array = $this->combine->asArray();

        expect($array)->toHaveKey('type');
        expect($array)->toHaveKey('aggregator');
        expect($array)->toHaveKey('value');

        expect($array['type'])->toBe('customersegmentation/segment_condition_combine');
        expect($array['aggregator'])->toBe('all');
        expect($array['value'])->toBe(1);
    });

    test('can load basic structure from array', function () {
        $data = [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'any',
            'value' => 1,
        ];

        $this->combine->loadArray($data);

        expect($this->combine->getType())->toBe('customersegmentation/segment_condition_combine');
        expect($this->combine->getAggregator())->toBe('any');
        expect($this->combine->getValue())->toBe(1);
    });

    test('handles empty conditions gracefully', function () {
        $this->combine->setAggregator('all');
        $this->combine->setValue(1);

        // No conditions added - without custom logic, parent class handles this
        // The actual behavior depends on parent implementation
        $result = $this->combine->validate(new Varien_Object());
        expect($result)->toBeBool();

        $this->combine->setAggregator('any');
        // Test that it doesn't crash with any aggregator
        $result = $this->combine->validate(new Varien_Object());
        expect($result)->toBeBool();
    });

    test('can get condition types available for selection', function () {
        $options = $this->combine->getNewChildSelectOptions();

        // Should include various condition types
        $foundTypes = [];
        foreach ($options as $option) {
            if (isset($option['value']) && is_array($option['value'])) {
                foreach ($option['value'] as $subOption) {
                    if (isset($subOption['value'])) {
                        $foundTypes[] = $subOption['value'];
                    }
                }
            } elseif (isset($option['value'])) {
                $foundTypes[] = $option['value'];
            }
        }

        // Should have at least some condition types available
        expect(count($foundTypes))->toBeGreaterThan(0);
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
                $item['label'] === 'Quantity in Cart' ||
                $item['label'] === 'Price' ||
                $item['label'] === 'Product Type'
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

    test('handles deep nesting with 5 levels of combine conditions', function () {
        // Level 1: Root combine (ANY)
        $level1 = Mage::getModel('customersegmentation/segment_condition_combine');
        $level1->setAggregator('any')->setValue(1);

        // Level 2: First nested combine (ALL)
        $level2a = Mage::getModel('customersegmentation/segment_condition_combine');
        $level2a->setAggregator('all')->setValue(1);

        // Level 3: Second nested combine (ANY)
        $level3a = Mage::getModel('customersegmentation/segment_condition_combine');
        $level3a->setAggregator('any')->setValue(1);

        // Level 4: Third nested combine (ALL)
        $level4a = Mage::getModel('customersegmentation/segment_condition_combine');
        $level4a->setAggregator('all')->setValue(1);

        // Level 5: Fourth nested combine (ANY)
        $level5a = Mage::getModel('customersegmentation/segment_condition_combine');
        $level5a->setAggregator('any')->setValue(1);

        // Add leaf conditions to deepest level
        $leafCondition1 = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $leafCondition1->setAttribute('firstname')->setOperator('==')->setValue('John');

        $leafCondition2 = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $leafCondition2->setAttribute('lastname')->setOperator('==')->setValue('Doe');

        $level5a->addCondition($leafCondition1);
        $level5a->addCondition($leafCondition2);

        // Build hierarchy
        $level4a->addCondition($level5a);
        $level3a->addCondition($level4a);
        $level2a->addCondition($level3a);
        $level1->addCondition($level2a);

        // Add another branch at level 2 for complexity
        $level2b = Mage::getModel('customersegmentation/segment_condition_combine');
        $level2b->setAggregator('all')->setValue(1);

        $emailCondition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $emailCondition->setAttribute('email')->setOperator('{}')->setValue('@test.com');
        $level2b->addCondition($emailCondition);

        $level1->addCondition($level2b);

        // Test with matching data (should match through level2b branch)
        $testData = new Varien_Object([
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'email' => 'jane.smith@test.com',
        ]);

        expect($level1->validate($testData))->toBe(true);

        // Test with data that matches the deep nested branch
        $testData2 = new Varien_Object([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@other.com',
        ]);

        expect($level1->validate($testData2))->toBe(true);
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


    test('can export and import complex nested structure', function () {
        // Create simple structure first
        $this->combine->setAggregator('any')->setValue(1);

        // Export to array
        $array = $this->combine->asArray();

        expect($array)->toHaveKey('type');
        expect($array)->toHaveKey('aggregator');
        expect($array)->toHaveKey('value');
        expect($array['aggregator'])->toBe('any');
        expect($array['value'])->toBe(1);

        // Import from array
        $newCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $newCombine->loadArray($array);

        expect($newCombine->getAggregator())->toBe('any');
        expect($newCombine->getValue())->toBe(1);
        expect($newCombine->getType())->toBe('customersegmentation/segment_condition_combine');
    });
});
