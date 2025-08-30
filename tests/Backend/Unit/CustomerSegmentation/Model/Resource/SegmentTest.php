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

    test('can get matched customers for segment - core segmentation functionality', function () {
        // Create a test segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Customer Match Test');
        $segment->setIsActive(1);
        $segment->setWebsiteIds('1');
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        // Test the core segmentation functionality
        $matchedCustomers = $this->resource->getMatchingCustomerIds($segment);

        expect($matchedCustomers)->toBeArray();
    });

    test('can manage customer segment relationships - core segmentation functionality', function () {
        // Create test customers first
        $customerIds = [];
        $uniqueId = uniqid('unit_', true);
        for ($i = 0; $i < 2; $i++) {
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
        $segment->setWebsiteIds('1');
        $this->resource->save($segment);
        // Track created segment for cleanup
        $this->trackCreatedRecord('customer_segment', (int) $segment->getId());

        $segmentId = $segment->getId();

        // Test updating customer membership
        $this->resource->updateCustomerMembership($segment, $customerIds);

        // Verify relationships were saved
        foreach ($customerIds as $customerId) {
            $isInSegment = $this->resource->isCustomerInSegment((int) $segmentId, (int) $customerId);
            expect($isInSegment)->toBe(true);
        }

        // Test removing relationships
        $this->resource->updateCustomerMembership($segment, []);
        expect($this->resource->isCustomerInSegment((int) $segmentId, (int) $customerIds[0]))->toBe(false);
    });

    test('can get active segments by website - segmentation specific functionality', function () {
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

        // Test website-specific segment retrieval
        $website1SegmentIds = $this->resource->getActiveSegmentIds(1);
        expect($website1SegmentIds)->toBeArray();
        expect($website1SegmentIds)->toContain($segment1->getId());
    });
});
