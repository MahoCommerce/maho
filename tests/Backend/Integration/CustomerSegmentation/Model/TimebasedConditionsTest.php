<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Time-based Customer Conditions', function () {
    beforeEach(function () {
        createTimebasedTestData();
    });

    describe('days_since_last_order condition', function () {
        test('excludes canceled orders correctly', function () {
            $segment = createTimebasedTestSegment('Recent Order Customers', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '<=',
                'value' => '30',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                // Verify customer has non-canceled orders within 30 days
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled'])
                    ->setOrder('created_at', 'DESC');

                expect($orders->getSize())->toBeGreaterThan(0);

                $lastOrder = $orders->getFirstItem();
                $now = date('Y-m-d H:i:s');
                $daysDiff = (int) ((strtotime($now) - strtotime($lastOrder->getCreatedAt())) / 86400);
                expect($daysDiff)->toBeLessThanOrEqual(30);
            }
        });
    });

    describe('days_without_purchase condition', function () {
        test('includes customers with no orders when using > operator', function () {
            $segment = createTimebasedTestSegment('Days Without Purchase GT', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_without_purchase',
                'operator' => '>',
                'value' => '60',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Should include customers with no orders
            $hasCustomerWithNoOrders = false;

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                if ($orders->getSize() === 0) {
                    $hasCustomerWithNoOrders = true;
                } else {
                    $lastOrder = $orders->setOrder('created_at', 'DESC')->getFirstItem();
                    // Match the customer segmentation logic: use UTC time for consistency
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $daysDiff = (int) ((strtotime($currentDate) - strtotime($lastOrder->getCreatedAt())) / 86400);
                    expect($daysDiff)->toBeGreaterThanOrEqual(60); // Sample data creates orders ~60.x days ago
                }
            }

            // Should include at least one customer with no orders
            expect($hasCustomerWithNoOrders)->toBe(true);
        });

        test('excludes customers with no orders when using <= operator', function () {
            $segment = createTimebasedTestSegment('Days Without Purchase LTE', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_without_purchase',
                'operator' => '<=',
                'value' => '30',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                // Should only include customers with orders
                expect($orders->getSize())->toBeGreaterThan(0);

                $lastOrder = $orders->setOrder('created_at', 'DESC')->getFirstItem();
                $now = date('Y-m-d H:i:s');
                $daysDiff = (int) ((strtotime($now) - strtotime($lastOrder->getCreatedAt())) / 86400);
                expect($daysDiff)->toBeLessThanOrEqual(30);
            }
        });
    });

    describe('edge cases and boundary conditions', function () {
        test('handles customers with both canceled and non-canceled orders', function () {
            $segment = createTimebasedTestSegment('Mixed Order States', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Should only consider non-canceled orders
                $nonCanceledOrders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                expect($nonCanceledOrders->getSize())->toBeGreaterThan(0);
            }
        });
    });

    // Helper functions
    function createTimebasedTestData(): void
    {
        $uniqueId = uniqid('timebased_', true);

        // Create customers with various scenarios
        $customers = [
            // Customer with recent login and recent order (5 days ago)
            [
                'firstname' => 'Recent',
                'lastname' => 'Activity',
                'email' => "recent.activity.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'login_days_ago' => 3,
                'orders' => [
                    ['days_ago' => 5, 'total' => 100.00, 'status' => 'pending'],
                    ['days_ago' => 15, 'total' => 50.00, 'status' => 'pending'],
                ],
            ],
            // Customer with old login, recent order (90 days ago login, 7 days ago order)
            [
                'firstname' => 'Old',
                'lastname' => 'Login',
                'email' => "old.login.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-120 days')),
                'login_days_ago' => 90,
                'orders' => [
                    ['days_ago' => 7, 'total' => 75.00, 'status' => 'processing'],
                ],
            ],
            // Customer with no login, recent order
            [
                'firstname' => 'No',
                'lastname' => 'Login',
                'email' => "no.login.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'login_days_ago' => null,
                'orders' => [
                    ['days_ago' => 2, 'total' => 200.00, 'status' => 'pending'],
                ],
            ],
            // Customer with no orders, recent login
            [
                'firstname' => 'No',
                'lastname' => 'Orders',
                'email' => "no.orders.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
                'login_days_ago' => 10,
                'orders' => [],
            ],
            // Customer with no orders, no login (registered 200 days ago)
            [
                'firstname' => 'Completely',
                'lastname' => 'Inactive',
                'email' => "inactive.{$uniqueId}@timetest.com",
                'group_id' => 2,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-200 days')),
                'login_days_ago' => null,
                'orders' => [],
            ],
            // Customer with multiple orders for frequency testing
            [
                'firstname' => 'High',
                'lastname' => 'Frequency',
                'email' => "high.frequency.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-365 days')),
                'login_days_ago' => 5,
                'orders' => [
                    ['days_ago' => 10, 'total' => 50.00, 'status' => 'pending'],
                    ['days_ago' => 40, 'total' => 75.00, 'status' => 'pending'],
                    ['days_ago' => 70, 'total' => 100.00, 'status' => 'pending'],
                    ['days_ago' => 100, 'total' => 25.00, 'status' => 'pending'],
                ],
            ],
            // Customer with single order
            [
                'firstname' => 'Single',
                'lastname' => 'Order',
                'email' => "single.order.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
                'login_days_ago' => 50,
                'orders' => [
                    ['days_ago' => 80, 'total' => 150.00, 'status' => 'pending'],
                ],
            ],
            // Customer with mixed order states (including canceled)
            [
                'firstname' => 'Mixed',
                'lastname' => 'Orders',
                'email' => "mixed.orders.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-150 days')),
                'login_days_ago' => 30,
                'orders' => [
                    ['days_ago' => 20, 'total' => 100.00, 'status' => 'pending'],
                    ['days_ago' => 25, 'total' => 75.00, 'status' => 'canceled'], // Should be ignored
                    ['days_ago' => 100, 'total' => 50.00, 'status' => 'pending'],
                ],
            ],
            // Customer registered today
            [
                'firstname' => 'Today',
                'lastname' => 'Registered',
                'email' => "today.registered.{$uniqueId}@timetest.com",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'login_days_ago' => null,
                'orders' => [],
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId($customerData['group_id']);
            $customer->setWebsiteId($customerData['website_id']);
            $customer->setCreatedAt($customerData['created_at']);
            $customer->save();

            // Create login record if specified
            if (isset($customerData['login_days_ago']) && $customerData['login_days_ago'] !== null) {
                $loginAt = date('Y-m-d H:i:s', strtotime("-{$customerData['login_days_ago']} days"));

                $resource = Mage::getSingleton('core/resource');
                $adapter = $resource->getConnection('core_write');
                $logTable = $resource->getTableName('log/customer');

                $adapter->insert($logTable, [
                    'customer_id' => $customer->getId(),
                    'login_at' => $loginAt,
                    'logout_at' => null,
                    'store_id' => 1,
                ]);
            }

            // Create orders if specified
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderData['total']);
                $order->setBaseGrandTotal($orderData['total']);

                $order->setData('state', orderStateForStatus($orderData['status']));
                $order->setStatus($orderData['status']);

                $order->setStoreId(1);

                $orderCreatedAt = date('Y-m-d H:i:s', strtotime("-{$orderData['days_ago']} days"));
                $order->setCreatedAt($orderCreatedAt);

                $order->save();

            }
        }
    }

    function createTimebasedTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
        $segment->setDescription('Timebased test segment for ' . $name);
        $segment->setIsActive();
        $segment->setWebsiteIds([1]);
        $segment->setCustomerGroupIds([0, 1, 2, 3]);
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->setPriority(10);
        $segment->save();

        return $segment;
    }
});
