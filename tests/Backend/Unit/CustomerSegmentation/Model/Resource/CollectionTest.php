<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Customer Segment Collection', function () {
    beforeEach(function () {
        $this->collection = Mage::getResourceModel('customersegmentation/segment_collection');
    });

    test('can filter by website - segmentation specific functionality', function () {
        createTestSegments();

        $this->collection->addWebsiteFilter(1);
        $this->collection->load();

        foreach ($this->collection as $segment) {
            expect($segment->getWebsiteIds())->toContain(1);
        }
    });

    test('adds the number of members of each segment to the select', function () {
        $customerIds = [];
        $uniqueId = uniqid('count_', true);
        for ($i = 0; $i < 2; $i++) {
            $customer = Mage::getModel('customer/customer')
                ->setEmail("count.customer.{$uniqueId}.{$i}@test.com")
                ->setFirstname('Count')
                ->setLastname('Customer')
                ->setWebsiteId(1)
                ->setGroupId(1);
            $customer->save();
            $customerIds[] = (int) $customer->getId();
        }

        $segment = Mage::getModel('customersegmentation/segment')
            ->setName('Customer Count ' . $uniqueId)
            ->setWebsiteIds([1]);
        $segment->save();
        Mage::getResourceModel('customersegmentation/segment')->updateCustomerMembership($segment, $customerIds);

        $this->collection->addCustomerCountToSelect();
        $this->collection->getSelect()->where('main_table.segment_id = ?', (int) $segment->getId());
        $this->collection->load();

        expect($this->collection->count())->toBe(1)
            ->and((int) $this->collection->getFirstItem()->getData('customer_count'))->toBe(2);
    });

    // Helper method to create test segments
    function createTestSegments()
    {
        $segments = [
            [
                'name' => 'VIP Customers',
                'description' => 'High value customers',
                'is_active' => 1,
                'website_ids' => '1,2',
                'customer_group_ids' => '1,2',
                'priority' => 10,
                'refresh_mode' => 'auto',
                'refresh_status' => 'completed',
            ],
            [
                'name' => 'New Customers',
                'description' => 'Recently registered customers',
                'is_active' => 1,
                'website_ids' => '1',
                'customer_group_ids' => '0',
                'priority' => 5,
                'refresh_mode' => 'manual',
                'refresh_status' => 'pending',
            ],
            [
                'name' => 'Inactive Customers',
                'description' => 'Customers who haven\'t ordered recently',
                'is_active' => 0,
                'website_ids' => '1,2',
                'customer_group_ids' => '0,1,2',
                'priority' => 1,
                'refresh_mode' => 'auto',
                'refresh_status' => 'error',
            ],
        ];

        foreach ($segments as $segmentData) {
            $segment = Mage::getModel('customersegmentation/segment');
            foreach ($segmentData as $key => $value) {
                $segment->setData($key, $value);
            }
            $segment->save();
        }
    }
});
