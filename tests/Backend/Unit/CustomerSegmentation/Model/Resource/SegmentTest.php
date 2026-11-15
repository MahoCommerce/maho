<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Segment Resource Model', function () {
    beforeEach(function () {
        $this->resource = Mage::getResourceModel('customersegmentation/segment');
    });

    test('matches an active customer of the website of a segment without conditions', function () {
        $customer = Mage::getModel('customer/customer')
            ->setEmail('match.' . uniqid('unit_', true) . '@test.com')
            ->setFirstname('Match')
            ->setLastname('Customer')
            ->setWebsiteId(1)
            ->setGroupId(1);
        $customer->save();

        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Customer Match Test');
        $segment->setIsActive();
        $segment->setWebsiteIds([1]);
        $this->resource->save($segment);

        $matchedCustomers = $this->resource->getMatchingCustomerIds($segment);

        expect($matchedCustomers)->toContain((int) $customer->getId());
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
            $customerIds[] = $customer->getId();
        }

        // Create a test segment
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('Relationship Test');
        $segment->setIsActive();
        $segment->setWebsiteIds([1]);
        $this->resource->save($segment);

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
        $segment1->setIsActive();
        $segment1->setWebsiteIds([1]);
        $this->resource->save($segment1);

        $segment2 = Mage::getModel('customersegmentation/segment');
        $segment2->setName('Website 2 Segment');
        $segment2->setIsActive();
        $segment2->setWebsiteIds([2]);
        $this->resource->save($segment2);

        // Test website-specific segment retrieval
        $website1SegmentIds = $this->resource->getActiveSegmentIds(1);
        expect($website1SegmentIds)->toBeArray();
        expect($website1SegmentIds)->toContain((int) $segment1->getId());
    });
});
