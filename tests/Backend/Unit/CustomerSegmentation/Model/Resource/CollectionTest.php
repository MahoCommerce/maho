<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
            $websiteIds = explode(',', $segment->getWebsiteIds());
            expect($websiteIds)->toContain('1');
        }
    });

    test('can add customer count to select - segmentation specific functionality', function () {
        createTestSegments();

        $this->collection->addCustomerCountToSelect();
        $this->collection->load();

        foreach ($this->collection as $segment) {
            // Should have customer count field after joining
            expect($segment->hasData('customer_count'))->toBe(true);
        }
    });

    test('can filter segments needing refresh - segmentation specific functionality', function () {
        createTestSegments();

        // Add filter for segments that need refresh (pending status or old refresh date)
        $this->collection->addFieldToFilter(
            ['refresh_status', 'last_refresh_at'],
            [
                ['eq' => 'pending'],
                ['lt' => date('Y-m-d H:i:s', strtotime('-1 day'))],
            ],
        );
        $this->collection->load();

        foreach ($this->collection as $segment) {
            $needsRefresh = ($segment->getRefreshStatus() === 'pending') ||
                           ($segment->getLastRefreshAt() &&
                            strtotime($segment->getLastRefreshAt()) < strtotime('-1 day'));
            expect($needsRefresh)->toBe(true);
        }
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
