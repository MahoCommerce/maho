<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Segment Resource Model', function () {
    beforeEach(function () {
        $this->resource = Mage::getResourceModel('customersegmentation/segment');
        $this->useTransactions();
    });

    test('can create resource model instance', function () {
        expect($this->resource)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Resource_Segment::class);
        expect($this->resource)->toBeInstanceOf(Mage_Core_Model_Resource_Db_Abstract::class);
    });

    test('has correct main table and id field', function () {
        expect($this->resource->getMainTable())->toBe('customer_segment');
        expect($this->resource->getIdFieldName())->toBe('segment_id');
    });

    test('can save segment data', function () {
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Test Segment Resource');
        $segment->setDescription('Testing resource model');
        $segment->setIsActive(1);
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');

        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        expect($segment->getId())->toBeGreaterThan(0);

        // Reload segment to get timestamps from database
        $segmentId = $segment->getId();
        $reloadedSegment = Mage::getModel('customersegmentation/segment');
        $this->resource->load($reloadedSegment, $segmentId);

        expect($reloadedSegment->getCreatedAt())->not()->toBeEmpty();
        expect($reloadedSegment->getUpdatedAt())->not()->toBeEmpty();
    });

    test('can load segment data', function () {
        // First save a segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Load Test Segment');
        $segment->setDescription('Testing load functionality');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        $segmentId = $segment->getId();

        // Now load it
        $loadedSegment = Mage::getModel('customersegmentation/segment');
        $this->resource->load($loadedSegment, $segmentId);

        expect($loadedSegment->getId())->toBe($segmentId);
        expect($loadedSegment->getName())->toBe('Load Test Segment');
        expect($loadedSegment->getDescription())->toBe('Testing load functionality');
        expect((int) $loadedSegment->getIsActive())->toBe(1);
    });

    test('can update segment data', function () {
        // Create and save segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Update Test Segment');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        // Reload to get initial timestamps
        $segmentId = $segment->getId();
        $this->resource->load($segment, $segmentId);
        $originalUpdatedAt = $segment->getUpdatedAt();

        // Update the segment
        $segment->setName('Updated Segment Name');
        $segment->setDescription('Updated description');
        sleep(2); // Ensure timestamp difference (MySQL precision is 1 second)
        $this->resource->save($segment);

        // Reload to get updated timestamps
        $this->resource->load($segment, $segmentId);

        expect($segment->getName())->toBe('Updated Segment Name');
        expect($segment->getDescription())->toBe('Updated description');
        expect($segment->getUpdatedAt())->toBeGreaterThanOrEqual($originalUpdatedAt);
    });

    test('can delete segment data', function () {
        // Create and save segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Delete Test Segment');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        $segmentId = $segment->getId();
        expect($segmentId)->toBeGreaterThan(0);

        // Delete the segment
        $this->resource->delete($segment);

        // Try to load deleted segment
        $loadedSegment = Mage::getModel('customersegmentation/segment');
        $this->resource->load($loadedSegment, $segmentId);

        expect($loadedSegment->getId())->toBeNull();
    });

    test('can get matched customers for segment', function () {
        // Create a test segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Customer Match Test');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        // Use existing API method
        $matchedCustomers = $this->resource->getMatchingCustomerIds($segment);

        expect($matchedCustomers)->toBeArray();
    });

    test('can save customer segment relationships', function () {
        // Create test customers first
        $customerIds = [];
        $uniqueId = uniqid('unit_', true);
        for ($i = 0; $i < 3; $i++) {
            $customer = Mage::getModel('customer/customer');
            $customer->setEmail("test.customer.{$uniqueId}.{$i}@test.com");
            $customer->setFirstname('Test');
            $customer->setLastname('Customer');
            $customer->setWebsiteId(1);
            $customer->setGroupId(1);
            $customer->save();
            // Track created customer for cleanup
            $this->trackCreatedRecord('customer_entity', (int) $customer->getId());
            $customerIds[] = $customer->getId();
        }

        // Create a test segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Relationship Test');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        $segmentId = $segment->getId();

        // Use existing API method to update customer membership
        $this->resource->updateCustomerMembership($segment, $customerIds);

        // Verify relationships were saved by checking if customers are in segment
        foreach ($customerIds as $customerId) {
            $isInSegment = $this->resource->isCustomerInSegment((int) $segmentId, (int) $customerId);
            expect($isInSegment)->toBe(true);
        }
    });

    test('can remove customer segment relationships', function () {
        // Create test customers first
        $customerIds = [];
        $uniqueId = uniqid('unit_remove_', true);
        for ($i = 0; $i < 3; $i++) {
            $customer = Mage::getModel('customer/customer');
            $customer->setEmail("test.remove.customer.{$uniqueId}.{$i}@test.com");
            $customer->setFirstname('Test');
            $customer->setLastname('Remove');
            $customer->setWebsiteId(1);
            $customer->setGroupId(1);
            $customer->save();
            // Track created customer for cleanup
            $this->trackCreatedRecord('customer_entity', (int) $customer->getId());
            $customerIds[] = $customer->getId();
        }

        // Create a test segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Remove Relationship Test');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        $segmentId = $segment->getId();

        // First save relationships
        $this->resource->updateCustomerMembership($segment, $customerIds);

        // Verify they were added
        expect($this->resource->isCustomerInSegment((int) $segmentId, (int) $customerIds[0]))->toBe(true);

        // Then remove them by updating with empty array
        $this->resource->updateCustomerMembership($segment, []);

        // Verify relationships were removed
        expect($this->resource->isCustomerInSegment((int) $segmentId, (int) $customerIds[0]))->toBe(false);
    });

    test('handles database operations correctly', function () {
        // Test that segments can be saved with minimal required data
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Minimal Segment'); // Name is required by business logic, not database

        $result = $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());
        expect($result)->toBeInstanceOf(Maho_CustomerSegmentation_Model_Resource_Segment::class);
        expect($segment->getId())->toBeGreaterThan(0);
    });

    test('can get segments by website', function () {
        // Create segments with different website assignments
        $segment1 = Mage::getModel('customersegmentation/segment');
        $segment1->setName('Website 1 Segment');
        $segment1->setIsActive(1);
        $segment1->setWebsiteIds('1');
        $this->resource->save($segment1);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment1->getId());

        $segment2 = Mage::getModel('customersegmentation/segment');
        $segment2->setName('Website 2 Segment');
        $segment2->setIsActive(1);
        $segment2->setWebsiteIds('2');
        $this->resource->save($segment2);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment2->getId());

        // Use existing API to get active segment IDs for website 1
        $website1SegmentIds = $this->resource->getActiveSegmentIds(1);
        expect($website1SegmentIds)->toBeArray();
        expect($website1SegmentIds)->toContain($segment1->getId());
    });

    test('can update segment statistics', function () {
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Statistics Test Segment');
        $segment->setIsActive(1);
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        $segmentId = $segment->getId();
        $customerCount = 25;

        // Manually update segment statistics using existing API
        $segment->setMatchedCustomersCount($customerCount);
        $segment->setLastRefreshAt(date('Y-m-d H:i:s'));
        $segment->setRefreshStatus('completed');
        $this->resource->save($segment);

        // Reload segment and verify count
        $updatedSegment = Mage::getModel('customersegmentation/segment');
        $this->resource->load($updatedSegment, $segmentId);

        expect((int) $updatedSegment->getMatchedCustomersCount())->toBe($customerCount);
    });

    test('can handle serialized conditions data', function () {
        $conditions = [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                    'attribute' => 'email',
                    'operator' => '{}',
                    'value' => '@example.com',
                ],
            ],
        ];

        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Conditions Test Segment');
        $segment->setIsActive(1);
        $segment->setConditionsSerialized(serialize($conditions));

        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        // Reload and verify conditions were saved correctly
        $loadedSegment = Mage::getModel('customersegmentation/segment');
        $this->resource->load($loadedSegment, $segment->getId());

        $savedConditions = unserialize($loadedSegment->getConditionsSerialized());
        expect($savedConditions)->toBeArray();
        expect($savedConditions['type'])->toBe('customersegmentation/segment_condition_combine');
        expect($savedConditions['aggregator'])->toBe('all');
    });
});
