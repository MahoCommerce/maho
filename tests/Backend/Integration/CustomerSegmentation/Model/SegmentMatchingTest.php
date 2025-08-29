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

    // Helper methods
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
