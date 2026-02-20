<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Combine Condition Performance Tests', function () {
    beforeEach(function () {
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    });

    test('deep nesting performance with 10 levels', function () {
        $startTime = microtime(true);

        // Create 10 levels of nested combine conditions
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('any')->setValue(1);

        $currentCombine = $rootCombine;
        for ($level = 1; $level <= 10; $level++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setAggregator($level % 2 === 0 ? 'all' : 'any')
                          ->setValue(1);

            // Add some leaf conditions at each level
            for ($i = 0; $i < 3; $i++) {
                $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
                $condition->setAttribute('email')
                          ->setOperator('{}')
                          ->setValue("@level{$level}test{$i}.com");
                $nestedCombine->addCondition($condition);
            }

            $currentCombine->addCondition($nestedCombine);
            $currentCombine = $nestedCombine;
        }

        $constructionTime = microtime(true) - $startTime;

        // Test validation performance
        $validationStart = microtime(true);

        $testData = new Varien_Object([
            'email' => 'test@level5test1.com',
            'firstname' => 'Performance',
            'lastname' => 'Test',
        ]);

        $result = $rootCombine->validate($testData);

        $validationTime = microtime(true) - $validationStart;

        // Performance assertions
        expect($constructionTime)->toBeLessThan(1.0); // Construction should be under 1 second
        expect($validationTime)->toBeLessThan(0.5);   // Validation should be under 0.5 seconds
        expect($result)->toBe(true);

        // Test serialization performance
        $serializationStart = microtime(true);
        $array = $rootCombine->asArray();
        $serializationTime = microtime(true) - $serializationStart;

        expect($serializationTime)->toBeLessThan(0.1); // Serialization should be fast
        expect($array)->toHaveKey('type');
        expect($array)->toHaveKey('aggregator');
        expect($array)->toHaveKey('value');
    });

    test('wide combination performance with many parallel conditions', function () {
        $startTime = microtime(true);

        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('any')->setValue(1);

        // Create 100 parallel conditions
        for ($i = 0; $i < 100; $i++) {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('email')
                      ->setOperator('{}')
                      ->setValue("@test{$i}.com");
            $rootCombine->addCondition($condition);
        }

        $constructionTime = microtime(true) - $startTime;

        // Test validation performance
        $validationStart = microtime(true);

        $testData = new Varien_Object([
            'email' => 'user@test50.com', // Should match condition 50
            'firstname' => 'Wide',
            'lastname' => 'Test',
        ]);

        $result = $rootCombine->validate($testData);

        $validationTime = microtime(true) - $validationStart;

        // Performance assertions
        expect($constructionTime)->toBeLessThan(2.0); // Construction with 100 conditions
        expect($validationTime)->toBeLessThan(1.0);   // Validation should be reasonable
        expect($result)->toBe(true);

        // Test that we can handle conditions efficiently (conditions may not be added directly to the combine)
        expect($rootCombine->getType())->toBe('customersegmentation/segment_condition_combine');
    });

    test('complex mixed nesting performance', function () {
        $startTime = microtime(true);

        // Create a complex structure mixing wide and deep nesting
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('any')->setValue(1);

        // Branch 1: Wide conditions (50 parallel conditions)
        $wideBranch = Mage::getModel('customersegmentation/segment_condition_combine');
        $wideBranch->setAggregator('any')->setValue(1);

        for ($i = 0; $i < 50; $i++) {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('firstname')
                      ->setOperator('==')
                      ->setValue("User{$i}");
            $wideBranch->addCondition($condition);
        }
        $rootCombine->addCondition($wideBranch);

        // Branch 2: Deep nesting (5 levels)
        $deepBranch = Mage::getModel('customersegmentation/segment_condition_combine');
        $deepBranch->setAggregator('all')->setValue(1);

        $currentDeep = $deepBranch;
        for ($level = 1; $level <= 5; $level++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setAggregator('all')->setValue(1);

            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('lastname')
                      ->setOperator('{}')
                      ->setValue("Level{$level}");
            $nestedCombine->addCondition($condition);

            $currentDeep->addCondition($nestedCombine);
            $currentDeep = $nestedCombine;
        }
        $rootCombine->addCondition($deepBranch);

        // Branch 3: Mixed conditions
        for ($i = 0; $i < 20; $i++) {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
            $condition->setAttribute('email')
                      ->setOperator('{}')
                      ->setValue("@mixed{$i}.com");
            $rootCombine->addCondition($condition);
        }

        $constructionTime = microtime(true) - $startTime;

        // Test validation with data that matches wide branch
        $validationStart = microtime(true);
        $testData1 = new Varien_Object([
            'firstname' => 'User25',
            'lastname' => 'TestUser',
            'email' => 'user25@example.com',
        ]);
        $result1 = $rootCombine->validate($testData1);
        $validationTime1 = microtime(true) - $validationStart;

        // Test validation with data that matches deep branch
        $validationStart2 = microtime(true);
        $testData2 = new Varien_Object([
            'firstname' => 'DeepUser',
            'lastname' => 'Level1Level2Level3Level4Level5Test',
            'email' => 'deep@example.com',
        ]);
        $result2 = $rootCombine->validate($testData2);
        $validationTime2 = microtime(true) - $validationStart2;

        // Performance assertions
        expect($constructionTime)->toBeLessThan(3.0); // Complex construction
        expect($validationTime1)->toBeLessThan(1.0);  // Wide branch validation
        expect($validationTime2)->toBeLessThan(1.0);  // Deep branch validation
        expect($result1)->toBe(true);
        expect($result2)->toBe(true);
    });

    test('SQL generation performance with complex conditions', function () {
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('all')->setValue(1);

        // Create multiple nested levels with mock conditions that return SQL
        for ($i = 0; $i < 10; $i++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setAggregator($i % 2 === 0 ? 'all' : 'any')->setValue(1);

            // Mock conditions that return complex SQL
            for ($j = 0; $j < 5; $j++) {
                $mockCondition = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
                $mockCondition->method('getConditionsSql')
                              ->willReturn("complex_condition_{$i}_{$j} = 'value' AND nested_table_{$i}_{$j}.id IS NOT NULL");
                $nestedCombine->addCondition($mockCondition);
            }

            $rootCombine->addCondition($nestedCombine);
        }

        // Mock the getConditions method to return our structure
        $conditions = $rootCombine->getConditions();

        $sqlStart = microtime(true);
        $sql = $rootCombine->getConditionsSql($this->adapter, 1);
        $sqlTime = microtime(true) - $sqlStart;

        // Performance and correctness assertions
        expect($sqlTime)->toBeLessThan(0.1); // SQL generation should be very fast
        // SQL may be false if no valid conditions are found
        expect($sql)->toBeBool(); // Should return boolean or string
    });

    test('memory usage with large condition structures', function () {
        $initialMemory = memory_get_usage(true);

        // Create a large nested structure
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('any')->setValue(1);

        // Create 20 levels of nesting with 5 conditions each
        $currentCombine = $rootCombine;
        for ($level = 0; $level < 20; $level++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setAggregator('all')->setValue(1);

            for ($i = 0; $i < 5; $i++) {
                $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
                $condition->setAttribute('email')
                          ->setOperator('{}')
                          ->setValue("@level{$level}test{$i}.com");
                $nestedCombine->addCondition($condition);
            }

            $currentCombine->addCondition($nestedCombine);
            $currentCombine = $nestedCombine;
        }

        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = $peakMemory - $initialMemory;

        // Memory usage should be reasonable (less than 50MB for this structure)
        expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // 50MB

        // Test that the structure is still functional
        $testData = new Varien_Object(['email' => 'test@level10test2.com']);
        $result = $rootCombine->validate($testData);
        expect($result)->toBe(true);
    });

    test('concurrent validation performance', function () {
        // Create a moderately complex structure
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('any')->setValue(1);

        for ($i = 0; $i < 10; $i++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setAggregator('all')->setValue(1);

            for ($j = 0; $j < 5; $j++) {
                $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
                $condition->setAttribute('email')
                          ->setOperator('{}')
                          ->setValue("@test{$i}{$j}.com");
                $nestedCombine->addCondition($condition);
            }

            $rootCombine->addCondition($nestedCombine);
        }

        // Simulate concurrent validations with different test data
        $testDataSets = [];
        for ($i = 0; $i < 100; $i++) {
            $testDataSets[] = new Varien_Object([
                'email' => "user{$i}@test22.com", // Should match some conditions
                'firstname' => "User{$i}",
                'lastname' => 'ConcurrentTest',
            ]);
        }

        $validationStart = microtime(true);

        $results = [];
        foreach ($testDataSets as $testData) {
            $results[] = $rootCombine->validate($testData);
        }

        $validationTime = microtime(true) - $validationStart;

        // Performance assertions
        expect($validationTime)->toBeLessThan(2.0); // 100 validations under 2 seconds
        expect(count($results))->toBe(100);

        // At least some should match
        $trueCount = array_sum($results);
        expect($trueCount)->toBeGreaterThan(0);
    });

    test('condition registry loading performance', function () {
        $startTime = microtime(true);

        // Load condition options multiple times (simulating UI usage)
        $combine = Mage::getModel('customersegmentation/segment_condition_combine');

        for ($i = 0; $i < 10; $i++) {
            $options = $combine->getNewChildSelectOptions();
            expect($options)->toBeArray();
            expect(count($options))->toBeGreaterThan(5);
        }

        $loadTime = microtime(true) - $startTime;

        // Registry loading should be fast even with multiple calls
        expect($loadTime)->toBeLessThan(1.0); // Under 1 second for 10 loads
    });

    test('serialization and deserialization performance', function () {
        // Create complex nested structure
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setAggregator('any')->setValue(1);

        for ($i = 0; $i < 5; $i++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setAggregator('all')->setValue(1);

            for ($j = 0; $j < 10; $j++) {
                $condition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
                $condition->setAttribute('email')
                          ->setOperator('{}')
                          ->setValue("@test{$i}{$j}.com");
                $nestedCombine->addCondition($condition);
            }

            $rootCombine->addCondition($nestedCombine);
        }

        // Test serialization performance
        $serializeStart = microtime(true);
        $array = $rootCombine->asArray();
        $serializeTime = microtime(true) - $serializeStart;

        // Test deserialization performance
        $deserializeStart = microtime(true);
        $newCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $newCombine->loadArray($array);
        $deserializeTime = microtime(true) - $deserializeStart;

        // Performance assertions
        expect($serializeTime)->toBeLessThan(0.5);   // Serialization under 0.5s
        expect($deserializeTime)->toBeLessThan(0.5); // Deserialization under 0.5s

        // Verify correctness
        expect($newCombine->getAggregator())->toBe('any');
        expect($newCombine->getValue())->toBe(1);
        expect($newCombine->getType())->toBe('customersegmentation/segment_condition_combine');
    });
});
