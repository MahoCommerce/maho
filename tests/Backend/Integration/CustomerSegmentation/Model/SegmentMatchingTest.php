<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Segment Matching Integration', function () {
    beforeEach(function () {
        $this->useTransactions();
        createMatchingTestCustomers();
        createMatchingTestOrders();
    });

    test('can match customers by email condition', function () {
        $segment = createMatchingTestSegment('Email Domain Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@test.com',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify that matched customers actually have @test.com emails
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect($customer->getEmail())->toContain('@test.com');
        }
    });

    test('can match customers by order total condition', function () {
        $segment = createMatchingTestSegment('High Value Customers', [
            'type' => 'customersegmentation/segment_condition_order_attributes',
            'attribute' => 'grand_total',
            'operator' => '>=',
            'value' => '100',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify customers have orders meeting the criteria
        foreach ($matchedCustomers as $customerId) {
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId);

            $hasHighValueOrder = false;
            foreach ($orders as $order) {
                if ($order->getGrandTotal() >= 100) {
                    $hasHighValueOrder = true;
                    break;
                }
            }
            expect($hasHighValueOrder)->toBe(true);
        }
    });

    test('can match customers with combine conditions using AND logic', function () {
        $segment = createMatchingTestSegment('VIP Email Customers', [
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

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify customers meet both conditions
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

    test('can match customers with combine conditions using OR logic', function () {
        $segment = createMatchingTestSegment('Email or High Value Customers', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'any',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                    'attribute' => 'firstname',
                    'operator' => '==',
                    'value' => 'John',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_order_attributes',
                    'attribute' => 'grand_total',
                    'operator' => '>=',
                    'value' => '200',
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify customers meet at least one condition
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);

            $meetsFirstCondition = ($customer->getFirstname() === 'John');

            $meetsSecondCondition = false;
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId);

            foreach ($orders as $order) {
                if ($order->getGrandTotal() >= 200) {
                    $meetsSecondCondition = true;
                    break;
                }
            }

            expect($meetsFirstCondition || $meetsSecondCondition)->toBe(true);
        }
    });

    test('can match customers by customer group condition', function () {
        $segment = createMatchingTestSegment('General Customer Group', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'group_id',
            'operator' => '==',
            'value' => '1',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect((int) $customer->getGroupId())->toBe(1);
        }
    });

    test('can match customers by registration date condition', function () {
        $yesterdayDate = date('Y-m-d', strtotime('-1 day'));

        $segment = createMatchingTestSegment('Recent Customers', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'created_at',
            'operator' => '>=',
            'value' => $yesterdayDate,
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $customerDate = date('Y-m-d', strtotime($customer->getCreatedAt()));
            expect($customerDate)->toBeGreaterThanOrEqual($yesterdayDate);
        }
    });

    test('returns empty array when no customers match conditions', function () {
        $segment = createMatchingTestSegment('Non-matching Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@nonexistent.domain',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBe(0);
    });

    test('can handle complex nested conditions', function () {
        $segment = createMatchingTestSegment('Complex Nested Segment', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'any',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'all',
                    'value' => 1,
                    'conditions' => [
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'firstname',
                            'operator' => '==',
                            'value' => 'John',
                        ],
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'email',
                            'operator' => '{}',
                            'value' => '@test.com',
                        ],
                    ],
                ],
                [
                    'type' => 'customersegmentation/segment_condition_order_attributes',
                    'attribute' => 'grand_total',
                    'operator' => '>=',
                    'value' => '500',
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
    });

    test('segment matching respects website restrictions', function () {
        $segment = createMatchingTestSegment('Website Specific Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@test.com',
        ]);

        // Set specific website restriction
        $segment->setWebsiteIds('1');
        $segment->save();

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify all matched customers belong to website 1
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect((int) $customer->getWebsiteId())->toBe(1);
        }
    });

    test('can match customers with zero lifetime orders', function () {
        // Create customers specifically for CLV testing
        createClvTestCustomers();

        $segment = createMatchingTestSegment('Zero Orders Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_orders',
            'operator' => '==',
            'value' => '0',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify matched customers actually have 0 orders
        foreach ($matchedCustomers as $customerId) {
            $orderCount = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
                ->getSize();
            expect($orderCount)->toBe(0);
        }
    });

    test('can match customers with lifetime orders less than or equal to 1', function () {
        createClvTestCustomers();

        $segment = createMatchingTestSegment('LE 1 Orders Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_orders',
            'operator' => '<=',
            'value' => '1',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify matched customers have <= 1 orders
        foreach ($matchedCustomers as $customerId) {
            $orderCount = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
                ->getSize();
            expect($orderCount)->toBeLessThanOrEqual(1);
        }
    });

    test('can match customers with lifetime orders greater than 1', function () {
        createClvTestCustomers();

        $segment = createMatchingTestSegment('GT 1 Orders Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_orders',
            'operator' => '>',
            'value' => '1',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify matched customers have > 1 orders
        foreach ($matchedCustomers as $customerId) {
            $orderCount = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
                ->getSize();
            expect($orderCount)->toBeGreaterThan(1);
        }
    });

    test('can match customers with zero lifetime sales', function () {
        createClvTestCustomers();

        $segment = createMatchingTestSegment('Zero Sales Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_sales',
            'operator' => '==',
            'value' => '0',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify matched customers have 0 lifetime sales
        foreach ($matchedCustomers as $customerId) {
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']]);

            $totalSales = 0.0;
            foreach ($orders as $order) {
                $totalSales += (float) $order->getGrandTotal();
            }
            expect($totalSales)->toBe(0.0);
        }
    });

    test('can match customers with lifetime sales less than or equal to 100', function () {
        createClvTestCustomers();

        $segment = createMatchingTestSegment('LE 100 Sales Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'lifetime_sales',
            'operator' => '<=',
            'value' => '100',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify matched customers have <= 100 lifetime sales
        foreach ($matchedCustomers as $customerId) {
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']]);

            $totalSales = 0.0;
            foreach ($orders as $order) {
                $totalSales += (float) $order->getGrandTotal();
            }
            expect($totalSales)->toBeLessThanOrEqual(100.0);
        }
    });

    test('can match customers with zero average order value', function () {
        createClvTestCustomers();

        $segment = createMatchingTestSegment('Zero AOV Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'average_order_value',
            'operator' => '==',
            'value' => '0',
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify matched customers have 0 average order value (no orders)
        foreach ($matchedCustomers as $customerId) {
            $orderCount = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
                ->getSize();
            expect($orderCount)->toBe(0);
        }
    });

    test('CLV conditions work correctly with combine conditions', function () {
        createClvTestCustomers();

        $segment = createMatchingTestSegment('Zero Orders General Group', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                    'attribute' => 'group_id',
                    'operator' => '==',
                    'value' => '1',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_customer_clv',
                    'attribute' => 'lifetime_orders',
                    'operator' => '==',
                    'value' => '0',
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify customers meet both conditions
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect((int) $customer->getGroupId())->toBe(1);

            $orderCount = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
                ->getSize();
            expect($orderCount)->toBe(0);
        }
    });

    // Helper methods
    function createClvTestCustomers(): void
    {
        $uniqueId = uniqid('clv_test_', true);

        // Create customers with different order scenarios
        $customers = [
            // Customer with 0 orders
            [
                'firstname' => 'Zero',
                'lastname' => 'Orders',
                'email' => "zero.orders.{$uniqueId}@clvtest.com",
                'group_id' => 1,
                'website_id' => 1,
                'order_count' => 0,
            ],
            // Another customer with 0 orders (different group)
            [
                'firstname' => 'Another',
                'lastname' => 'Zero',
                'email' => "another.zero.{$uniqueId}@clvtest.com",
                'group_id' => 2,
                'website_id' => 1,
                'order_count' => 0,
            ],
            // Customer with exactly 1 order
            [
                'firstname' => 'Single',
                'lastname' => 'Order',
                'email' => "single.order.{$uniqueId}@clvtest.com",
                'group_id' => 1,
                'website_id' => 1,
                'order_count' => 1,
            ],
            // Customer with 2 orders
            [
                'firstname' => 'Double',
                'lastname' => 'Orders',
                'email' => "double.orders.{$uniqueId}@clvtest.com",
                'group_id' => 1,
                'website_id' => 1,
                'order_count' => 2,
            ],
            // Customer with 3 orders
            [
                'firstname' => 'Triple',
                'lastname' => 'Orders',
                'email' => "triple.orders.{$uniqueId}@clvtest.com",
                'group_id' => 1,
                'website_id' => 1,
                'order_count' => 3,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId($customerData['group_id']);
            $customer->setWebsiteId($customerData['website_id']);
            $customer->save();

            test()->trackCreatedRecord('customer_entity', (int) $customer->getId());

            // Create orders for this customer
            $orderCount = $customerData['order_count'];
            $orderValues = [25.50, 75.00, 150.00]; // Different order values

            for ($i = 0; $i < $orderCount; $i++) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderValues[$i % count($orderValues)]);
                $order->setStatus('pending');
                $order->setState(Mage_Sales_Model_Order::STATE_NEW);
                $order->setStoreId(1);
                $order->save();

                test()->trackCreatedRecord('sales_flat_order', (int) $order->getId());
            }
        }
    }

    function createMatchingTestCustomers(): void
    {
        $uniqueId = uniqid('test_', true);
        $customers = [
            [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => "john.doe.matching.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Jane',
                'lastname' => 'Smith',
                'email' => "jane.smith.matching.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Bob',
                'lastname' => 'Johnson',
                'email' => "bob.johnson.matching.{$uniqueId}@example.org",
                'group_id' => 2,
                'website_id' => 1,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setData($customerData);
            $customer->save();
            // Track created customer for cleanup
            test()->trackCreatedRecord('customer_entity', (int) $customer->getId());
        }
    }

    function createMatchingTestOrders(): void
    {
        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('email', ['like' => '%@test.com%']);

        $orderData = [
            ['grand_total' => 75.50, 'status' => 'pending'],
            ['grand_total' => 150.00, 'status' => 'processing'],
            ['grand_total' => 300.00, 'status' => 'pending'],
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
                // Track created order for cleanup
                test()->trackCreatedRecord('sales_flat_order', (int) $order->getId());

                $orderIndex++;
            }
        }
    }

    function createMatchingTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
        $segment->setConditionsSerialized(serialize($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->save();
        // Track created segment for cleanup
        test()->trackCreatedRecord('customer_segment', (int) $segment->getId());

        return $segment;
    }
});
