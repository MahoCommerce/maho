<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Segment Refresh Operations', function () {
    beforeEach(function () {
        createRefreshTestCustomers();
        createRefreshTestOrders();
    });

    test('can refresh segment manually', function () {
        $segment = createRefreshTestSegment('Manual Refresh Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@test.com',
        ]);

        // Set manual refresh mode
        $segment->setRefreshMode(Maho_CustomerSegmentation_Model_Segment::MODE_MANUAL);
        $segment->setRefreshStatus(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING);
        $segment->save();

        expect($segment->getRefreshMode())->toBe('manual');
        expect($segment->getRefreshStatus())->toBe('pending');

        // Perform manual refresh
        $result = $segment->refreshCustomers();

        expect($result)->toBe($segment);
        expect($segment->getRefreshStatus())->toBe('completed');
        expect($segment->getLastRefreshAt())->not()->toBeEmpty();
        expect((int) $segment->getMatchedCustomersCount())->toBeGreaterThan(0);
    });

    test('refresh updates customer count correctly', function () {
        $segment = createRefreshTestSegment('Count Update Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@test.com',
        ]);

        // Initial state
        expect((int) $segment->getMatchedCustomersCount())->toBe(0);

        // Refresh segment
        $segment->refreshCustomers();

        // Should have updated count
        $expectedCount = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('email', ['like' => '%@test.com%'])
            ->getSize();

        expect((int) $segment->getMatchedCustomersCount())->toBe($expectedCount);
    });

    test('completes the refresh of a segment whose condition has an unknown attribute', function () {
        $segment = createRefreshTestSegment('Invalid Conditions Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'nonexistent_attribute',
            'operator' => '==',
            'value' => 'test',
        ]);

        $segment->refreshCustomers();

        $reloaded = Mage::getModel('customersegmentation/segment')->load($segment->getId());
        expect($reloaded->getRefreshStatus())->toBe('completed');
    });

    test('refresh preserves customer-segment relationships', function () {
        $segment = createRefreshTestSegment('Relationship Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@test.com',
        ]);

        // First refresh
        $segment->refreshCustomers();
        $firstRefreshCount = (int) $segment->getMatchedCustomersCount();

        expect($firstRefreshCount)->toBeGreaterThan(0);

        // Get current relationships
        $resource = $segment->getResource();
        $relationships = $resource->getCustomerSegmentRelations($segment->getId());
        expect(count($relationships))->toBe($firstRefreshCount);

        // Second refresh should maintain same relationships if conditions unchanged
        $segment->refreshCustomers();
        $secondRefreshCount = (int) $segment->getMatchedCustomersCount();

        expect($secondRefreshCount)->toBe($firstRefreshCount);

        $newRelationships = $resource->getCustomerSegmentRelations($segment->getId());
        expect(count($newRelationships))->toBe(count($relationships));
    });

    test('refresh updates last refresh timestamp', function () {
        $segment = createRefreshTestSegment('Timestamp Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'firstname',
            'operator' => '==',
            'value' => 'John',
        ]);

        $originalTimestamp = $segment->getLastRefreshAt();

        sleep(1); // Ensure timestamp difference
        $segment->refreshCustomers();

        $newTimestamp = $segment->getLastRefreshAt();
        expect($newTimestamp)->not()->toBe($originalTimestamp);
        expect(strtotime($newTimestamp))->toBeGreaterThan(strtotime($originalTimestamp ?: '1970-01-01'));
    });

    test('refresh handles empty result set gracefully', function () {
        $segment = createRefreshTestSegment('Empty Result Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@nonexistentdomain.invalid',
        ]);

        $result = $segment->refreshCustomers();

        expect($result)->toBe($segment);
        expect($segment->getRefreshStatus())->toBe('completed');
        expect((int) $segment->getMatchedCustomersCount())->toBe(0);
        expect($segment->getLastRefreshAt())->not()->toBeEmpty();
    });

    test('refresh respects segment active status', function () {
        $segment = createRefreshTestSegment('Inactive Segment Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'firstname',
            'operator' => '==',
            'value' => 'John',
        ]);

        // Test inactive segments - they should not be included in auto refresh
        $segment->setIsActive(false);
        $segment->setRefreshMode('auto');
        $segment->setRefreshStatus('pending');
        $segment->save();

        expect($segment->getIsActive())->toBeFalse();
        expect($segment->getRefreshMode())->toBe('auto');
        expect($segment->getRefreshStatus())->toBe('pending');

        // Manual refresh should still work even for inactive segments
        $segment->refreshCustomers();
        expect($segment->getRefreshStatus())->toBe('completed')
            ->and((int) $segment->getMatchedCustomersCount())->toBe(0);
    });

    // Helper methods
    function createRefreshTestCustomers(): void
    {
        $uniqueId = uniqid('refresh_', true);
        $customers = [
            [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => "john.doe.refresh.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Jane',
                'lastname' => 'Smith',
                'email' => "jane.smith.refresh.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Bob',
                'lastname' => 'Johnson',
                'email' => "bob.johnson.refresh.{$uniqueId}@example.org",
                'group_id' => 2,
                'website_id' => 1,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setData($customerData);
            $customer->save();
        }
    }

    function createRefreshTestOrders(): void
    {
        $customerCollection = Mage::getModel('customer/customer')->getCollection();

        $orderData = [
            ['grand_total' => 75.50, 'status' => 'pending'],
            ['grand_total' => 150.00, 'status' => 'processing'],
            ['grand_total' => 25.00, 'status' => 'pending'],
        ];

        $orderIndex = 0;
        foreach ($customerCollection as $customer) {
            if ($orderIndex < count($orderData)) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderData[$orderIndex]['grand_total']);
                $order->setBaseGrandTotal($orderData[$orderIndex]['grand_total']);
                $order->setData('state', orderStateForStatus($orderData[$orderIndex]['status']));
                $order->setStatus($orderData[$orderIndex]['status']);
                $order->save();

                $orderIndex++;
            }
        }
    }

    function createRefreshTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
    {
        // Wrap single condition in combine structure if needed
        if (isset($conditions['type']) && $conditions['type'] !== 'customersegmentation/segment_condition_combine') {
            $conditions = [
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'all',
                'value' => 1,
                'conditions' => [$conditions],
            ];
        }

        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName($name);
        $segment->setDescription('Test segment for ' . $name);
        $segment->setIsActive();
        $segment->setWebsiteIds([1]); // Base website
        $segment->setCustomerGroupIds([0, 1, 2, 3]); // All customer groups
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->setPriority(10);
        $segment->save();

        return $segment;
    }
});
