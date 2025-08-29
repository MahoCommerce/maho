<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Segment Model', function () {
    beforeEach(function () {
        $this->segment = Mage::getModel('customersegmentation/segment');
    });

    test('can create new segment instance', function () {
        expect($this->segment)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment::class);
        expect($this->segment->getId())->toBeNull();
    });

    test('has correct constants defined', function () {
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING)->toBe('pending');
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_PROCESSING)->toBe('processing');
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_COMPLETED)->toBe('completed');
        expect(Maho_CustomerSegmentation_Model_Segment::STATUS_ERROR)->toBe('error');

        expect(Maho_CustomerSegmentation_Model_Segment::MODE_AUTO)->toBe('auto');
        expect(Maho_CustomerSegmentation_Model_Segment::MODE_MANUAL)->toBe('manual');
    });

    test('can set and get basic attributes', function () {
        $this->segment->setName('Test Segment');
        $this->segment->setDescription('Test segment description');
        $this->segment->setIsActive(1);

        expect($this->segment->getName())->toBe('Test Segment');
        expect($this->segment->getDescription())->toBe('Test segment description');
        expect((int) $this->segment->getIsActive())->toBe(1);
    });

    test('can set and get refresh attributes', function () {
        $this->segment->setRefreshMode(Maho_CustomerSegmentation_Model_Segment::MODE_AUTO);
        $this->segment->setRefreshStatus(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING);
        $this->segment->setPriority(10);

        expect($this->segment->getRefreshMode())->toBe('auto');
        expect($this->segment->getRefreshStatus())->toBe('pending');
        expect((int) $this->segment->getPriority())->toBe(10);
    });

    test('can handle website and customer group IDs', function () {
        $websiteIds = '1,2,3';
        $customerGroupIds = '0,1,2';

        $this->segment->setWebsiteIds($websiteIds);
        $this->segment->setCustomerGroupIds($customerGroupIds);

        expect($this->segment->getWebsiteIds())->toBe($websiteIds);
        expect($this->segment->getCustomerGroupIds())->toBe($customerGroupIds);
    });

    test('can serialize and deserialize conditions', function () {
        $conditions = [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [],
        ];

        $this->segment->setConditionsSerialized(serialize($conditions));
        expect($this->segment->getConditionsSerialized())->toBe(serialize($conditions));
    });

    test('has correct resource model', function () {
        expect($this->segment->getResourceName())->toBe('customersegmentation/segment');
        expect($this->segment->getResource())->toBeInstanceOf(Maho_CustomerSegmentation_Model_Resource_Segment::class);
    });

    test('extends rule model abstract', function () {
        expect($this->segment)->toBeInstanceOf(Mage_Rule_Model_Abstract::class);
    });

    test('can get conditions combine model', function () {
        $conditionsModel = $this->segment->getConditions();
        expect($conditionsModel)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
    });

    test('validates segment data correctly', function () {
        // Test invalid segment (no name) - should throw exception
        $this->segment->setDescription('Test');
        expect(fn() => $this->segment->validate())->toThrow(Mage_Core_Exception::class);

        // Test valid segment
        $this->segment->setName('Valid Segment');
        $this->segment->setWebsiteIds('1'); // Required by validation
        $this->segment->setIsActive(1);
        expect($this->segment->validate())->toBe(true);
    });

    test('can set matched customers count', function () {
        $count = 150;
        $this->segment->setMatchedCustomersCount($count);
        expect((int) $this->segment->getMatchedCustomersCount())->toBe($count);
    });

    test('can set last refresh timestamp', function () {
        $timestamp = '2025-01-15 10:30:00';
        $this->segment->setLastRefreshAt($timestamp);
        expect($this->segment->getLastRefreshAt())->toBe($timestamp);
    });

    test('can check if segment is active', function () {
        $this->segment->setIsActive(1);
        expect($this->segment->getIsActive())->toBe(1);

        $this->segment->setIsActive(0);
        expect($this->segment->getIsActive())->toBe(0);
    });

    test('can handle empty conditions gracefully', function () {
        $this->segment->setConditionsSerialized('');
        $conditions = $this->segment->getConditions();
        expect($conditions)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
    });
});
