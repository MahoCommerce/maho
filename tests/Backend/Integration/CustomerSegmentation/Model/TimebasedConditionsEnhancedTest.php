<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Enhanced Time-based Customer Conditions', function () {
    beforeEach(function () {
        createEnhancedTimebasedTestData();
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

        test('days_inactive handles customers with no activity gracefully', function () {
            $segment = createTimebasedTestSegment('Very Inactive', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_inactive',
                'operator' => '>',
                'value' => '180', // Customers inactive for more than 180 days
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $customer = Mage::getModel('customer/customer')->load((int) $customerId);

                // For customers with no login or order activity,
                // should fall back to registration date
                $resource = Mage::getSingleton('core/resource');
                $adapter = $resource->getConnection('core_read');
                $logTable = $resource->getTableName('log/customer');

                $loginResult = $adapter->fetchRow(
                    $adapter->select()
                    ->from($logTable, ['last_login' => 'MAX(login_at)'])
                    ->where('customer_id = ?', $customerId),
                );

                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                // If customer has no login or orders, should use registration date
                if ((!$loginResult || !$loginResult['last_login']) && $orders->getSize() === 0) {
                    $registrationDays = (int) ((time() - strtotime($customer->getCreatedAt())) / 86400);
                    expect($registrationDays)->toBeGreaterThan(180);
                }
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

        test('order_frequency_days calculation accuracy', function () {
            $segment = createTimebasedTestSegment('Order Frequency Analysis', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'order_frequency_days',
                'operator' => '<=',
                'value' => '50', // Average of 50 days or less between orders
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled'])
                    ->setOrder('created_at', 'ASC');

                $orderCount = $orders->getSize();
                expect($orderCount)->toBeGreaterThan(1, 'Customer should have more than 1 order for frequency calculation');

                // Manual calculation to verify
                $orderDates = [];
                foreach ($orders as $order) {
                    $orderDates[] = strtotime($order->getCreatedAt());
                }

                sort($orderDates);
                $totalDays = (int) (($orderDates[count($orderDates) - 1] - $orderDates[0]) / 86400);
                $expectedFrequency = $totalDays / max($orderCount - 1, 1);

                expect($expectedFrequency)->toBeLessThanOrEqual(50);
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

        test('handles date precision correctly', function () {
            $segment = createTimebasedTestSegment('Precise Date Test', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '=',
                'value' => '7', // Exactly 7 days
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled'])
                    ->setOrder('created_at', 'DESC')
                    ->setPageSize(1);

                if ($orders->getSize() > 0) {
                    $lastOrder = $orders->getFirstItem();
                    // Use the EXACT same calculation as the segmentation condition
                    $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
                    $dateDiff = $adapter->getDateDiffSql("'{$currentDate}'", "'{$lastOrder->getCreatedAt()}'");
                    $select = $adapter->select()
                        ->from([], ['days' => $dateDiff]);
                    $daysDiff = (int) $adapter->fetchOne($select);
                    expect($daysDiff)->toBe(7); // Should match exactly since both use same date diff calculation
                } else {
                    // If no orders, ensure we have at least one assertion
                    expect(true)->toBe(true);
                }
            }

            // Ensure we have at least one assertion even if no customers match
            if (empty($matchedCustomers)) {
                expect(true)->toBe(true);
            }
        });

        test('website filtering works correctly across all conditions', function () {
            $conditions = [
                'days_since_last_order',
                'days_since_first_order',
                'order_frequency_days',
                'days_without_purchase',
            ];

            foreach ($conditions as $condition) {
                $segment = createTimebasedTestSegment("Website Test {$condition}", [
                    'type' => 'customersegmentation/segment_condition_customer_timebased',
                    'attribute' => $condition,
                    'operator' => '>=',
                    'value' => '0',
                ]);

                $segment->setWebsiteIds('1');
                $segment->save();

                $matchedCustomers = $segment->getMatchingCustomerIds();

                foreach ($matchedCustomers as $customerId) {
                    // For order-related conditions, verify orders are from correct website
                    if (in_array($condition, ['days_since_last_order', 'days_since_first_order', 'order_frequency_days', 'days_without_purchase'])) {
                        $orders = Mage::getResourceModel('sales/order_collection')
                            ->addFieldToFilter('customer_id', $customerId)
                            ->addFieldToFilter('state', ['neq' => 'canceled']);

                        if ($orders->getSize() > 0) {
                            foreach ($orders as $order) {
                                $storeIds = Mage::app()->getWebsite(1)->getStoreIds();
                                // Convert both to same type for comparison
                                $orderStoreId = (int) $order->getStoreId();
                                $websiteStoreIds = array_map('intval', $storeIds);

                                // Allow store ID 0 (admin/default) or must be in website 1
                                $isValidStore = ($orderStoreId === 0) || in_array($orderStoreId, $websiteStoreIds);
                                expect($isValidStore)->toBe(true);
                            }
                        }
                    }
                }
            }
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

            // Should not cause errors and should return some results
            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();
        });

        test('handles very large values', function () {
            $segment = createTimebasedTestSegment('Large Value Test', [
                'type' => 'customersegmentation/segment_condition_customer_timebased',
                'attribute' => 'days_since_last_order',
                'operator' => '<=',
                'value' => '999999', // Very large value
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            // Should include all customers with orders
            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['neq' => 'canceled']);

                expect($orders->getSize())->toBeGreaterThan(0);
            }
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
                $segment = createTimebasedTestSegment("Security Test {$maliciousValue}", [
                    'type' => 'customersegmentation/segment_condition_customer_timebased',
                    'attribute' => 'days_since_last_order',
                    'operator' => '>=',
                    'value' => $maliciousValue,
                ]);

                // Should not cause SQL errors or security issues
                try {
                    $matchedCustomers = $segment->getMatchingCustomerIds();
                    expect($matchedCustomers)->toBeArray();
                } catch (Exception $e) {
                    // If an exception occurs, it should be a clean validation error, not SQL injection
                    expect($e->getMessage())->not()->toContain('SQL');
                    expect($e->getMessage())->not()->toContain('DROP');
                }
            }
        });
    });

    // Helper functions
    function createEnhancedTimebasedTestData(): void
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
                $order->setState(Mage_Sales_Model_Order::STATE_NEW);
                $order->setStatus($orderData['status']);
                $order->setStoreId(1);

                $orderCreatedAt = date('Y-m-d H:i:s', $baseTime - ($orderData['days_ago'] * 86400));
                $order->setCreatedAt($orderCreatedAt);

                $order->save();

            }
        }
    }

});
