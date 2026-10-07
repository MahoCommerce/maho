<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
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
        $this->segment->setWebsiteIds([1]); // Required by validation
        $this->segment->setIsActive();
        expect($this->segment->validate())->toBe(true);
    });

    test('can handle empty conditions gracefully - segmentation specific functionality', function () {
        $this->segment->setConditionsSerialized('');
        $conditions = $this->segment->getConditions();
        expect($conditions)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Segment_Condition_Combine::class);
    });

    test('reads the website and customer group IDs from the column text and from the array of a load', function () {
        $this->segment->setData('website_ids', '1,2')->setData('customer_group_ids', '0,3');
        expect($this->segment->getWebsiteIds())->toBe([1, 2])
            ->and($this->segment->getCustomerGroupIds())->toBe([0, 3]);

        // _afterLoad() turns both columns into arrays of texts
        $this->segment->setData('website_ids', ['1', '2'])->setData('customer_group_ids', ['3']);
        expect($this->segment->getWebsiteIds())->toBe([1, 2])
            ->and($this->segment->getCustomerGroupIds())->toBe([3]);

        $this->segment->setData('website_ids')->setData('customer_group_ids', '');
        expect($this->segment->getWebsiteIds())->toBe([])
            ->and($this->segment->getCustomerGroupIds())->toBe([]);
    });

    test('limits the customers of a loaded segment to its customer groups', function () {
        $group = Mage::getModel('customer/group')
            ->setCode('Pest empty ' . substr(uniqid(), -6))
            ->setTaxClassId(3);
        $group->save();
        $segment = Mage::getModel('customersegmentation/segment')
            ->setName('Pest group scope ' . uniqid())
            ->setWebsiteIds([1])
            ->setCustomerGroupIds([(int) $group->getId()])
            ->setIsActive();
        $segment->save();

        try {
            $loaded = Mage::getModel('customersegmentation/segment')->load($segment->getId());
            expect($loaded->getMatchingCustomerIds())->toBe([]);
        } finally {
            $segment->delete();
            $group->delete();
        }
    });
});
