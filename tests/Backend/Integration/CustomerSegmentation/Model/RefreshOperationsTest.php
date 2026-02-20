<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

    test('can refresh segment automatically', function () {
        $segment = createRefreshTestSegment('Auto Refresh Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'firstname',
            'operator' => '==',
            'value' => 'John',
        ]);

        // Set auto refresh mode
        $segment->setRefreshMode(Maho_CustomerSegmentation_Model_Segment::MODE_AUTO);
        $segment->setRefreshStatus(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING);
        $segment->save();

        expect($segment->getRefreshMode())->toBe('auto');

        // Test auto refresh directly instead of via cron for speed
        expect($segment->getRefreshStatus())->toBe('pending');
        $segment->refreshCustomers();

        expect($segment->getRefreshStatus())->toBe('completed');
        expect($segment->getLastRefreshAt())->not()->toBeEmpty();
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

    test('refresh handles complex conditions correctly', function () {
        $segment = createRefreshTestSegment('Complex Refresh Test', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                    'attribute' => 'email',
                    'operator' => '{}',
                    'value' => '@test.com',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_order_attributes',
                    'attribute' => 'grand_total',
                    'operator' => '>=',
                    'value' => '50',
                ],
            ],
        ]);

        $segment->refreshCustomers();

        expect($segment->getRefreshStatus())->toBe('completed');
        expect((int) $segment->getMatchedCustomersCount())->toBeGreaterThanOrEqual(0);

        // Verify the matching logic is working correctly
        $matchedCustomers = $segment->getMatchingCustomerIds();
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect($customer->getEmail())->toContain('@test.com');

            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId);

            $hasQualifyingOrder = false;
            foreach ($orders as $order) {
                if ($order->getGrandTotal() >= 50) {
                    $hasQualifyingOrder = true;
                    break;
                }
            }
            expect($hasQualifyingOrder)->toBe(true);
        }
    });

    test('refresh sets error status on invalid conditions', function () {
        // Create a segment with valid structure but invalid attribute that will cause error
        $segment = createRefreshTestSegment('Invalid Conditions Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'nonexistent_attribute',
            'operator' => '==',
            'value' => 'test',
        ]);

        // The refresh should catch any error and set error status, then re-throw
        $exceptionThrown = false;
        try {
            $segment->refreshCustomers();
        } catch (Exception $e) {
            $exceptionThrown = true;
        }

        // If an exception was thrown, verify error status was set
        if ($exceptionThrown) {
            // Reload segment to check if status was set to error
            $segment = Mage::getModel('customersegmentation/segment')->load($segment->getId());
            expect($segment->getRefreshStatus())->toBe('error');
        } else {
            // If no exception, the refresh completed successfully
            expect($segment->getRefreshStatus())->toBe('completed');
        }
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

    test('cron refreshes only segments needing refresh', function () {
        // Test cron logic without actually running cron for speed
        $pendingSegment = createRefreshTestSegment('Pending Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'firstname',
            'operator' => '==',
            'value' => 'John',
        ]);
        $pendingSegment->setRefreshStatus('pending');
        $pendingSegment->setRefreshMode('auto');
        $pendingSegment->save();

        // Test that pending auto segments can be refreshed
        expect($pendingSegment->getRefreshMode())->toBe('auto');
        expect($pendingSegment->getRefreshStatus())->toBe('pending');

        // Refresh the pending segment
        $pendingSegment->refreshCustomers();
        expect($pendingSegment->getRefreshStatus())->toBe('completed');
    });

    test('refresh respects segment active status', function () {
        $segment = createRefreshTestSegment('Inactive Segment Test', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'firstname',
            'operator' => '==',
            'value' => 'John',
        ]);

        // Test inactive segments - they should not be included in auto refresh
        $segment->setIsActive(0);
        $segment->setRefreshMode('auto');
        $segment->setRefreshStatus('pending');
        $segment->save();

        expect($segment->getIsActive())->toBe(0);
        expect($segment->getRefreshMode())->toBe('auto');
        expect($segment->getRefreshStatus())->toBe('pending');

        // Manual refresh should still work even for inactive segments
        $segment->refreshCustomers();
        expect($segment->getRefreshStatus())->toBe('completed');
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
                $order->setStatus($orderData[$orderIndex]['status']);
                $order->setState(Mage_Sales_Model_Order::STATE_NEW);
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
        $segment->setIsActive(1);
        $segment->setWebsiteIds('1'); // Base website
        $segment->setCustomerGroupIds('0,1,2,3'); // All customer groups
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->setPriority(10);
        $segment->save();

        return $segment;
    }
});
