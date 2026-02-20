<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Segment Model', function () {
    beforeEach(function () {
        $this->segment = Mage::getModel('customersegmentation/segment');
    });

    test('has correct segmentation status constants', function () {
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING)->toBe('pending');
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_PROCESSING)->toBe('processing');
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_COMPLETED)->toBe('completed');
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_ERROR)->toBe('error');

        expect(Maho_CustomerSegmentation_Model_Segment::MODE_AUTO)->toBe('auto');
        expect(Maho_CustomerSegmentation_Model_Segment::MODE_MANUAL)->toBe('manual');
    });

    test('can get conditions combine model - segmentation specific functionality', function () {
        $conditionsModel = $this->segment->getConditions();
        expect($conditionsModel)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
    });

    test('validates segment data correctly - business logic validation', function () {
        // Test invalid segment (no name) - should throw exception
        $this->segment->setDescription('Test');
        expect(fn() => $this->segment->validate())->toThrow(Mage_Core_Exception::class);

        // Test valid segment
        $this->segment->setName('Valid Segment');
        $this->segment->setWebsiteIds('1'); // Required by validation
        $this->segment->setIsActive(1);
        expect($this->segment->validate())->toBe(true);
    });

    test('can handle empty conditions gracefully - segmentation specific functionality', function () {
        $this->segment->setConditionsSerialized('');
        $conditions = $this->segment->getConditions();
        expect($conditions)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
    });
});
