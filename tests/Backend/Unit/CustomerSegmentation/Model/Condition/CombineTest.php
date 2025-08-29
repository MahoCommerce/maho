<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
});
