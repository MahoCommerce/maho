<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Enhanced Time-based Customer Conditions', function () {
    beforeEach(function () {
        $this->customers = createEnhancedTimebasedTestData();
    });

    describe('complex fallback logic tests', function () {
        test('days_inactive fallback prioritizes most recent activity correctly', function () {
            // Test that days_inactive correctly uses the most recent activity between login, order, and registration
            $segment = createTimebasedTestSegment('Inactive Test', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_inactive',
                'operator' => '<=',
                'value' => '10', // Only customers active in last 10 days
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load((int) $customerId);

                // Get all activity timestamps
                $createdAt = strtotime($customer->getCreatedAt());
                $mostRecentActivity = $createdAt;

                // Check login activity
                $resource = Mage::getSingleton('core/resource');
                $adapter = $resource->getConnection('core_read');
                $logTable = $resource->getTableName('log/customer');

                $loginResult = $adapter->fetchRow(
                    $adapter->select()
                    ->from($logTable, ['last_login' => 'MAX(login_at)'])
                    ->where('customer_id = ?', $customerId),
                );

                if ($loginResult && $loginResult['last_login']) {
                    $mostRecentActivity = max($mostRecentActivity, strtotime($loginResult['last_login']));
                }

                // Check order activity
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled'])
                    ->setOrder('created_at', 'DESC')
                    ->setPageSize(1);

                if ($orders->getSize() > 0) {
                    $lastOrder = $orders->getFirstItem();
                    $mostRecentActivity = max($mostRecentActivity, strtotime($lastOrder->getCreatedAt()));
                }

                // Verify the calculation matches expected behavior
                $now = time();
                $daysDiff = (int) (($now - $mostRecentActivity) / 86400);
                expect($daysDiff)->toBeLessThanOrEqual(10);
            }
        });

        test('days_without_purchase logic handles edge cases correctly', function () {
            // Test the special logic for >= and > operators including customers with no orders
            $segmentGte = createTimebasedTestSegment('No Purchase GTE 30', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_without_purchase',
                'operator' => '>=',
                'value' => '30',
            ]);

            $matchedCustomersGte = $segmentGte->getMatchingCustomerIds();

            // Test that customers with no orders are included
            $customersWithNoOrders = 0;
            $customersWithOldOrders = 0;

            foreach ($matchedCustomersGte as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                if ($orders->getSize() === 0) {
                    $customersWithNoOrders++;
                } else {
                    $customersWithOldOrders++;
                    $lastOrder = $orders->setOrder('created_at', 'DESC')->getFirstItem();
                    // Match the customer segmentation logic: use UTC time for consistency
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $daysDiff = (int) ((strtotime($currentDate) - strtotime($lastOrder->getCreatedAt())) / 86400);
                    expect($daysDiff)->toBeGreaterThanOrEqual(29); // Sample data creates orders ~29.x days ago
                }
            }

            expect($customersWithNoOrders)->toBeGreaterThan(0, 'Should include customers with no orders');

            // Now test < operator which should exclude customers with no orders
            $segmentLt = createTimebasedTestSegment('Recent Purchase LT 30', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_without_purchase',
                'operator' => '<',
                'value' => '30',
            ]);

            $matchedCustomersLt = $segmentLt->getMatchingCustomerIds();

            foreach ($matchedCustomersLt as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                // Should only include customers with orders
                expect($orders->getSize())->toBeGreaterThan(0);

                $lastOrder = $orders->setOrder('created_at', 'DESC')->getFirstItem();
                $daysDiff = (int) ((time() - strtotime($lastOrder->getCreatedAt())) / 86400);
                expect($daysDiff)->toBeLessThan(30);
            }
        });
    });

    describe('boundary condition testing', function () {
        test('exact zero days calculations', function () {
            // Create customer with order today
            $todayCustomer = Mage::getModel('customer/customer');
            $todayCustomer->setFirstname('Today');
            $todayCustomer->setLastname('Order');
            $todayCustomer->setEmail('today.order.' . uniqid() . '@test.com');
            $todayCustomer->setGroupId(1);
            $todayCustomer->setWebsiteId(1);
            $todayCustomer->save();

            // Create order for today
            $order = Mage::getModel('sales/order');
            $order->setCustomerId($todayCustomer->getId());
            $order->setCustomerEmail($todayCustomer->getEmail());
            $order->setGrandTotal(100.00);
            $order->setBaseGrandTotal(100.00);
            $order->setState(Mage_Sales_Model_Order::STATE_NEW);
            $order->setStatus('pending');
            $order->setStoreId(1);
            $order->setCreatedAt(date('Y-m-d H:i:s')); // Today
            $order->save();

            // Test that this customer is found with 0 days condition
            $segment = createTimebasedTestSegment('Zero Days Since Order', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '<=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain((int) $todayCustomer->getId());
        });

        test('matches a customer whose last order is exactly 7 days old', function () {
            $segment = createTimebasedTestSegment('Precise Date Test', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '=',
                'value' => '7',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            expect($matchedCustomers)->toContain($this->customers['Precise Week'])
                ->and($matchedCustomers)->not->toContain($this->customers['Recent Order']);
        });
    });

    describe('negative and null value handling', function () {
        test('handles negative values gracefully', function () {
            $segment = createTimebasedTestSegment('Negative Value Test', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '>=',
                'value' => '-1', // Negative value should behave appropriately
            ]);

            expect($segment->getMatchingCustomerIds())->toContain($this->customers['Recent Order']);
        });
    });

    describe('sql injection and security tests', function () {
        test('handles malicious input safely', function () {
            $maliciousValues = [
                "'; DROP TABLE customer_segment; --",
                '1 OR 1=1',
                'NULL',
                '<script>alert("xss")</script>',
            ];

            foreach ($maliciousValues as $maliciousValue) {
                $condition = [
                    'type' => 'customersegmentation/segment_condition_customer_timebased',
                    'attribute' => 'days_since_last_order',
                    'operator' => '>=',
                ];
                $malicious = createTimebasedTestSegment("Security Test {$maliciousValue}", $condition + ['value' => $maliciousValue]);
                $integer = createTimebasedTestSegment('Security Test integer', $condition + ['value' => (string) (int) $maliciousValue]);

                $matched = $malicious->getMatchingCustomerIds();
                $expected = $integer->getMatchingCustomerIds();
                sort($matched);
                sort($expected);

                expect($matched)->toBe($expected);
            }
        });
    });

    // Helper functions
    /**
     * @return array<string, int> customer IDs keyed by "firstname lastname"
     */
    function createEnhancedTimebasedTestData(): array
    {
        $uniqueId = uniqid('enhanced_', true);
        $baseTime = time();

        $customers = [
            // Customer with recent activity (login 2 days ago, no orders)
            [
                'firstname' => 'Recent',
                'lastname' => 'Login',
                'email' => "recent.login.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (30 * 86400)),
                'login_days_ago' => 2,
                'orders' => [],
            ],
            // Customer with recent order (5 days ago), old login
            [
                'firstname' => 'Recent',
                'lastname' => 'Order',
                'email' => "recent.order.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (60 * 86400)),
                'login_days_ago' => 40,
                'orders' => [
                    ['days_ago' => 5, 'total' => 100.00, 'status' => 'pending'],
                ],
            ],
            // Customer with very old activity (should be in inactive category)
            [
                'firstname' => 'Old',
                'lastname' => 'Activity',
                'email' => "old.activity.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (200 * 86400)),
                'login_days_ago' => 195,
                'orders' => [
                    ['days_ago' => 190, 'total' => 50.00, 'status' => 'pending'],
                ],
            ],
            // Customer with no activity at all (registered but never logged in or ordered)
            [
                'firstname' => 'No',
                'lastname' => 'Activity',
                'email' => "no.activity.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (365 * 86400)),
                'login_days_ago' => null,
                'orders' => [],
            ],
            // Customer with frequent orders (for frequency testing)
            [
                'firstname' => 'Frequent',
                'lastname' => 'Buyer',
                'email' => "frequent.buyer.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (150 * 86400)),
                'login_days_ago' => 10,
                'orders' => [
                    ['days_ago' => 10, 'total' => 25.00, 'status' => 'pending'],
                    ['days_ago' => 35, 'total' => 30.00, 'status' => 'pending'],
                    ['days_ago' => 60, 'total' => 40.00, 'status' => 'pending'],
                    ['days_ago' => 85, 'total' => 35.00, 'status' => 'pending'],
                ], // Average frequency: 75 days span / 3 intervals = 25 days
            ],
            // Customer with infrequent orders (for frequency testing)
            [
                'firstname' => 'Infrequent',
                'lastname' => 'Buyer',
                'email' => "infrequent.buyer.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (400 * 86400)),
                'login_days_ago' => 50,
                'orders' => [
                    ['days_ago' => 50, 'total' => 100.00, 'status' => 'pending'],
                    ['days_ago' => 200, 'total' => 150.00, 'status' => 'pending'],
                ], // Average frequency: 150 days between orders
            ],
            // Customer with orders exactly 7 days ago (for precise testing)
            [
                'firstname' => 'Precise',
                'lastname' => 'Week',
                'email' => "precise.week.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (20 * 86400)),
                'login_days_ago' => null,
                'orders' => [
                    ['days_ago' => 7, 'total' => 75.00, 'status' => 'pending'],
                ],
            ],
            // Customer for testing edge cases (recent registration, recent order)
            [
                'firstname' => 'Edge',
                'lastname' => 'Case',
                'email' => "edge.case.{$uniqueId}@enhanced.test",
                'group_id' => 1,
                'website_id' => 1,
                'created_at' => date('Y-m-d H:i:s', $baseTime - (5 * 86400)),
                'login_days_ago' => 3,
                'orders' => [
                    ['days_ago' => 1, 'total' => 200.00, 'status' => 'pending'],
                ],
            ],
        ];

        $ids = [];
        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId($customerData['group_id']);
            $customer->setWebsiteId($customerData['website_id']);
            $customer->setCreatedAt($customerData['created_at']);
            $customer->save();
            $ids[$customerData['firstname'] . ' ' . $customerData['lastname']] = (int) $customer->getId();

            // Create login record if specified
            if (isset($customerData['login_days_ago']) && $customerData['login_days_ago'] !== null) {
                $loginAt = date('Y-m-d H:i:s', $baseTime - ($customerData['login_days_ago'] * 86400));

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

                $orderCreatedAt = date('Y-m-d H:i:s', $baseTime - ($orderData['days_ago'] * 86400));
                $order->setCreatedAt($orderCreatedAt);

                $order->save();

            }
        }

        return $ids;
    }

});
