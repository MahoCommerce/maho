<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Segment Matching Integration', function () {
    beforeEach(function () {
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
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Validate complex nested logic: (firstname=John AND email contains @test.com) OR (order >= 500)
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);

            // First condition: John AND @test.com
            $firstNameMatch = $customer->getFirstname() === 'John';
            $emailMatch = strpos($customer->getEmail(), '@test.com') !== false;
            $firstCondition = $firstNameMatch && $emailMatch;

            // Second condition: has order >= 500
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId);
            $secondCondition = false;
            foreach ($orders as $order) {
                if ($order->getGrandTotal() >= 500) {
                    $secondCondition = true;
                    break;
                }
            }

            // Customer must meet at least one condition (ANY aggregator)
            expect($firstCondition || $secondCondition)->toBe(true);
        }
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

    test('segment properly excludes customers from different websites', function () {
        createMultiWebsiteTestCustomers();

        // Create segment that matches email pattern but restricted to website 1 only
        $segment = createMatchingTestSegment('Website 1 Only Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@multiwebsite.com',
        ]);

        $segment->setWebsiteIds('1');
        $segment->setCustomerGroupIds('0,1,2,3'); // All groups
        $segment->save();

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Should only match customers from website 1, even though customers from website 2 have the same email pattern
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect((int) $customer->getWebsiteId())->toBe(1);
            expect($customer->getEmail())->toContain('@multiwebsite.com');
        }

        // Verify we have customers from website 2 that match the pattern but are excluded
        $website2Customers = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('website_id', 2)
            ->addFieldToFilter('email', ['like' => '%@multiwebsite.com%']);

        expect($website2Customers->getSize())->toBeGreaterThan(0);

        // Verify none of the website 2 customers are in the matched results
        foreach ($website2Customers as $customer) {
            expect($matchedCustomers)->not->toContain($customer->getId());
        }
    });

    test('segment matching respects customer group restrictions', function () {
        createMultiGroupTestCustomers();

        // Create segment restricted to customer group 1 only
        $segment = createMatchingTestSegment('Group 1 Only Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@grouptest.com',
        ]);

        $segment->setWebsiteIds('1'); // All websites
        $segment->setCustomerGroupIds('1'); // Only group 1
        $segment->save();

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify all matched customers are in group 1
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect((int) $customer->getGroupId())->toBe(1);
            expect($customer->getEmail())->toContain('@grouptest.com');
        }

        // Verify we have customers from other groups that match the pattern but are excluded
        $otherGroupCustomers = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('group_id', ['neq' => 1])
            ->addFieldToFilter('email', ['like' => '%@grouptest.com%']);

        expect($otherGroupCustomers->getSize())->toBeGreaterThan(0);

        // Verify none of the other group customers are in the matched results
        foreach ($otherGroupCustomers as $customer) {
            expect($matchedCustomers)->not->toContain($customer->getId());
        }
    });

    test('segment matching respects multiple customer group restrictions', function () {
        createMultiGroupTestCustomers();

        // Create segment restricted to customer groups 1 and 2
        $segment = createMatchingTestSegment('Groups 1 and 2 Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@grouptest.com',
        ]);

        $segment->setWebsiteIds('1');
        $segment->setCustomerGroupIds('1,2'); // Only groups 1 and 2
        $segment->save();

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify all matched customers are in groups 1 or 2
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect(in_array((int) $customer->getGroupId(), [1, 2]))->toBe(true);
            expect($customer->getEmail())->toContain('@grouptest.com');
        }

        // Verify customers from group 3 are excluded
        $group3Customers = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('group_id', 3)
            ->addFieldToFilter('email', ['like' => '%@grouptest.com%']);

        if ($group3Customers->getSize() > 0) {
            foreach ($group3Customers as $customer) {
                expect($matchedCustomers)->not->toContain($customer->getId());
            }
        }
    });

    test('segment matching works with combined website and customer group restrictions', function () {
        createMultiWebsiteAndGroupTestCustomers();

        // Create segment restricted to website 1 and customer group 2
        $segment = createMatchingTestSegment('Website 1 Group 2 Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@combined.com',
        ]);

        $segment->setWebsiteIds('1');
        $segment->setCustomerGroupIds('2');
        $segment->save();

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify all matched customers are in website 1 AND group 2
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect((int) $customer->getWebsiteId())->toBe(1);
            expect((int) $customer->getGroupId())->toBe(2);
            expect($customer->getEmail())->toContain('@combined.com');
        }

        // Verify we have customers that match email but are excluded due to wrong website or group
        $excludedCustomers = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('email', ['like' => '%@combined.com%']);

        // Add OR condition: different website OR different group (using multiple WHERE calls)
        $excludedCustomers->getSelect()->where('e.website_id != ?', 1);
        $excludedCustomers->getSelect()->orWhere('e.group_id != ?', 2);

        expect($excludedCustomers->getSize())->toBeGreaterThan(0);

        foreach ($excludedCustomers as $customer) {
            expect($matchedCustomers)->not->toContain($customer->getId());
        }
    });

    test('segment matching includes customer group 0 (not logged in) when specified', function () {
        createMultiGroupTestCustomers();

        // Create segment that includes group 0 (not logged in)
        $segment = createMatchingTestSegment('Including Group 0 Segment', [
            'type' => 'customersegmentation/segment_condition_customer_attributes',
            'attribute' => 'email',
            'operator' => '{}',
            'value' => '@grouptest.com',
        ]);

        $segment->setWebsiteIds('1');
        $segment->setCustomerGroupIds('0,1'); // Groups 0 and 1
        $segment->save();

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify matched customers are only in groups 0 or 1
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            expect(in_array((int) $customer->getGroupId(), [0, 1]))->toBe(true);
            expect($customer->getEmail())->toContain('@grouptest.com');
        }
    });

    test('can match customers with zero lifetime orders', function () {
        // Create customers specifically for CLV testing
        createClvTestCustomers();

        $segment = createMatchingTestSegment('Zero Orders Segment', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'number_of_orders',
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

    test('can match customers with zero orders using customer attributes condition', function () {
        // Create customers specifically for testing
        createClvTestCustomers();

        $segment = createMatchingTestSegment('Zero Orders Segment (Customer Attrs)', [
            'type' => 'customersegmentation/segment_condition_customer_clv',
            'attribute' => 'number_of_orders',
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
            'attribute' => 'number_of_orders',
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
            'attribute' => 'number_of_orders',
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
                    'attribute' => 'number_of_orders',
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
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->save();

        return $segment;
    }

    function createMultiWebsiteTestCustomers(): void
    {
        $uniqueId = uniqid('multiwebsite_', true);

        // Ensure we have website 2 available (create if doesn't exist)
        $website2 = Mage::getModel('core/website')->load(2);
        if (!$website2->getId()) {
            $website2->setCode('website2')
                ->setName('Test Website 2')
                ->setIsDefault(0)
                ->save();
        }

        // Create customers on website 1
        $website1Customers = [
            [
                'firstname' => 'Website1',
                'lastname' => 'Customer1',
                'email' => "w1.customer1.{$uniqueId}@multiwebsite.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Website1',
                'lastname' => 'Customer2',
                'email' => "w1.customer2.{$uniqueId}@multiwebsite.com",
                'group_id' => 2,
                'website_id' => 1,
            ],
        ];

        // Create customers on website 2
        $website2Customers = [
            [
                'firstname' => 'Website2',
                'lastname' => 'Customer1',
                'email' => "w2.customer1.{$uniqueId}@multiwebsite.com",
                'group_id' => 1,
                'website_id' => 2,
            ],
            [
                'firstname' => 'Website2',
                'lastname' => 'Customer2',
                'email' => "w2.customer2.{$uniqueId}@multiwebsite.com",
                'group_id' => 2,
                'website_id' => 2,
            ],
        ];

        $allCustomers = array_merge($website1Customers, $website2Customers);

        foreach ($allCustomers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setData($customerData);
            $customer->save();
        }
    }

    function createMultiGroupTestCustomers(): void
    {
        $uniqueId = uniqid('multigroup_', true);

        // Create customers in different groups (0 = not logged in, 1 = general, 2 = wholesale, 3 = retail)
        $customers = [
            [
                'firstname' => 'Group0',
                'lastname' => 'Customer',
                'email' => "group0.customer.{$uniqueId}@grouptest.com",
                'group_id' => 0,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Group1',
                'lastname' => 'Customer1',
                'email' => "group1.customer1.{$uniqueId}@grouptest.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Group1',
                'lastname' => 'Customer2',
                'email' => "group1.customer2.{$uniqueId}@grouptest.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Group2',
                'lastname' => 'Customer1',
                'email' => "group2.customer1.{$uniqueId}@grouptest.com",
                'group_id' => 2,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Group2',
                'lastname' => 'Customer2',
                'email' => "group2.customer2.{$uniqueId}@grouptest.com",
                'group_id' => 2,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Group3',
                'lastname' => 'Customer',
                'email' => "group3.customer.{$uniqueId}@grouptest.com",
                'group_id' => 3,
                'website_id' => 1,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setData($customerData);
            $customer->save();
        }
    }

    function createMultiWebsiteAndGroupTestCustomers(): void
    {
        $uniqueId = uniqid('combined_', true);

        // Ensure website 2 exists
        $website2 = Mage::getModel('core/website')->load(2);
        if (!$website2->getId()) {
            $website2->setCode('website2')
                ->setName('Test Website 2')
                ->setIsDefault(0)
                ->save();
        }

        // Create customers across different websites and groups
        $customers = [
            // Website 1 customers
            [
                'firstname' => 'W1G1',
                'lastname' => 'Customer',
                'email' => "w1g1.customer.{$uniqueId}@combined.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'W1G2',
                'lastname' => 'Customer',
                'email' => "w1g2.customer.{$uniqueId}@combined.com",
                'group_id' => 2,
                'website_id' => 1,
            ],
            [
                'firstname' => 'W1G3',
                'lastname' => 'Customer',
                'email' => "w1g3.customer.{$uniqueId}@combined.com",
                'group_id' => 3,
                'website_id' => 1,
            ],
            // Website 2 customers
            [
                'firstname' => 'W2G1',
                'lastname' => 'Customer',
                'email' => "w2g1.customer.{$uniqueId}@combined.com",
                'group_id' => 1,
                'website_id' => 2,
            ],
            [
                'firstname' => 'W2G2',
                'lastname' => 'Customer',
                'email' => "w2g2.customer.{$uniqueId}@combined.com",
                'group_id' => 2,
                'website_id' => 2,
            ],
            [
                'firstname' => 'W2G3',
                'lastname' => 'Customer',
                'email' => "w2g3.customer.{$uniqueId}@combined.com",
                'group_id' => 3,
                'website_id' => 2,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setData($customerData);
            $customer->save();
        }
    }
});
