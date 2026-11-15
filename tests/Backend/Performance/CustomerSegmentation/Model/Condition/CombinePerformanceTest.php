<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Combine Condition Performance Tests', function () {
    beforeEach(function () {
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    });

    test('builds the SQL of 10 nested combines with 5 conditions each in less than 0.1 seconds', function () {
        // A new combine has no prefix, and addCondition() drops a child of a combine without a prefix
        $rootCombine = Mage::getModel('customersegmentation/segment_condition_combine');
        $rootCombine->setPrefix('conditions')->setAggregator('all')->setValue(1);

        for ($i = 0; $i < 10; $i++) {
            $nestedCombine = Mage::getModel('customersegmentation/segment_condition_combine');
            $nestedCombine->setPrefix('conditions')->setAggregator($i % 2 === 0 ? 'all' : 'any')->setValue(1);

            for ($j = 0; $j < 5; $j++) {
                $mockCondition = $this->createMock(Maho_CustomerSegmentation_Model_Segment_Condition_Abstract::class);
                $mockCondition->method('getConditionsSql')
                              ->willReturn("complex_condition_{$i}_{$j} = 'value'");
                $nestedCombine->addCondition($mockCondition);
            }

            $rootCombine->addCondition($nestedCombine);
        }

        $sqlStart = microtime(true);
        $sql = $rootCombine->getConditionsSql($this->adapter, 1);
        $sqlTime = microtime(true) - $sqlStart;

        expect($sqlTime)->toBeLessThan(0.1)
            ->and($sql)->toContain("(complex_condition_0_0 = 'value') AND (complex_condition_0_1 = 'value')")
            ->and($sql)->toContain("(complex_condition_1_0 = 'value') OR (complex_condition_1_1 = 'value')")
            ->and($sql)->toContain("complex_condition_9_4 = 'value'");
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
});
