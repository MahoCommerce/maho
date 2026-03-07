<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Complex Condition Combinations', function () {
    beforeEach(function () {
        createComplexTestCustomers();
        createComplexTestOrders();
        createComplexTestCarts();
    });

    test('can handle deeply nested conditions', function () {
        $segment = createComplexTestSegment('Deeply Nested Test', [
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
                                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                                    'attribute' => 'firstname',
                                    'operator' => '==',
                                    'value' => 'Jane',
                                ],
                            ],
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
                    'value' => '200',
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Verify complex logic:
        // ((John OR Jane) AND email contains @test.com) OR has order >= $200
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);

            // Check first nested condition
            $firstNameMatch = in_array($customer->getFirstname(), ['John', 'Jane']);
            $emailMatch = strpos($customer->getEmail(), '@test.com') !== false;
            $firstCondition = $firstNameMatch && $emailMatch;

            // Check second condition
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId);
            $secondCondition = false;
            foreach ($orders as $order) {
                if ($order->getGrandTotal() >= 200) {
                    $secondCondition = true;
                    break;
                }
            }

            expect($firstCondition || $secondCondition)->toBe(true);
        }
    });

    test('can combine customer, order, and cart conditions', function () {
        $segment = createComplexTestSegment('Multi-Entity Conditions', [
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
                    'type' => 'customersegmentation/segment_condition_order_attributes',
                    'attribute' => 'status',
                    'operator' => '==',
                    'value' => 'pending',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_cart_items',
                    'attribute' => 'qty',
                    'operator' => '>',
                    'value' => '0',
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);

            // Verify customer group
            expect((int) $customer->getGroupId())->toBe(1);

            // Verify has pending orders
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('status', 'pending');
            expect($orders->getSize())->toBeGreaterThan(0);

            // Verify has cart items
            $cart = Mage::getModel('checkout/cart');
            $cart->setCustomer($customer);
            // Note: In real implementation, this would check active cart items
        }
    });

    test('can handle mixed aggregators correctly', function () {
        $segment = createComplexTestSegment('Mixed Aggregators', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all', // Main: ALL conditions must match
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'any', // Sub: ANY of these can match
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
                            'attribute' => 'firstname',
                            'operator' => '==',
                            'value' => 'Jane',
                        ],
                    ],
                ],
                [
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'all', // Sub: ALL of these must match
                    'value' => 1,
                    'conditions' => [
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'email',
                            'operator' => '{}',
                            'value' => '@test.com',
                        ],
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'group_id',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);

            // Must match: (John OR Jane) AND (@test.com AND group_id=1)
            $firstNameMatch = in_array($customer->getFirstname(), ['John', 'Jane']);
            $emailMatch = strpos($customer->getEmail(), '@test.com') !== false;
            $groupMatch = (int) $customer->getGroupId() === 1;

            expect($firstNameMatch)->toBe(true);
            expect($emailMatch && $groupMatch)->toBe(true);
        }
    });

    test('can handle time-based conditions with other criteria', function () {
        $segment = createComplexTestSegment('Time-Based Complex', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_customer_timebased',
                    'attribute' => 'days_since_first_order',
                    'operator' => '<=',
                    'value' => '365',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'any',
                    'value' => 1,
                    'conditions' => [
                        [
                            'type' => 'customersegmentation/segment_condition_order_attributes',
                            'attribute' => 'grand_total',
                            'operator' => '>=',
                            'value' => '100',
                        ],
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'group_id',
                            'operator' => '==',
                            'value' => '2',
                        ],
                    ],
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // This tests the structure rather than actual time calculations
        // since we'd need more complex time-based test data
    });

    test('can handle customer lifetime value conditions', function () {
        $segment = createComplexTestSegment('CLV Complex', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_customer_clv',
                    'attribute' => 'lifetime_sales',
                    'operator' => '>=',
                    'value' => '500',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'any',
                    'value' => 1,
                    'conditions' => [
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'email',
                            'operator' => '{}',
                            'value' => '@test.com',
                        ],
                        [
                            'type' => 'customersegmentation/segment_condition_customer_newsletter',
                            'attribute' => 'subscriber_status',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();
        expect(count($matchedCustomers))->toBeGreaterThan(0);

        // Verify each matched customer meets the conditions:
        // lifetime_sales >= 500 AND (email contains @test.com OR newsletter subscriber)
        foreach ($matchedCustomers as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);

            // Calculate lifetime sales
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('status', ['nin' => ['canceled', 'closed']]);

            $lifetimeSales = 0.0;
            foreach ($orders as $order) {
                $lifetimeSales += (float) $order->getGrandTotal();
            }

            // First condition: lifetime_sales >= 500
            expect($lifetimeSales)->toBeGreaterThanOrEqual(500.0);

            // Second condition: email contains @test.com OR newsletter subscriber
            $emailMatch = strpos($customer->getEmail(), '@test.com') !== false;

            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
            $newsletterMatch = $subscriber->isSubscribed();

            $secondCondition = $emailMatch || $newsletterMatch;
            expect($secondCondition)->toBe(true);
        }
    });

    test('can handle product-based conditions with customer criteria', function () {
        $segment = createComplexTestSegment('Product-Customer Complex', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'customersegmentation/segment_condition_product_viewed',
                    'attribute' => 'category_id',
                    'operator' => '()',
                    'value' => '1,2,3',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'any',
                    'value' => 1,
                    'conditions' => [
                        [
                            'type' => 'customersegmentation/segment_condition_product_wishlist',
                            'attribute' => 'wishlist_items_count',
                            'operator' => '>',
                            'value' => '0',
                        ],
                        [
                            'type' => 'customersegmentation/segment_condition_customer_attributes',
                            'attribute' => 'group_id',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
        ]);

        $matchedCustomers = $segment->getMatchingCustomerIds();

        expect($matchedCustomers)->toBeArray();

        // Tests structure validation for product-based conditions
    });

    test('handles invalid nested structures gracefully', function () {
        $segment = createComplexTestSegment('Invalid Structure Test', [
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [
                [
                    'type' => 'invalid_condition_type',
                    'attribute' => 'test',
                    'operator' => '==',
                    'value' => 'test',
                ],
                [
                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                    'attribute' => 'email',
                    'operator' => '{}',
                    'value' => '@test.com',
                ],
            ],
        ]);

        // Should handle invalid conditions gracefully
        $matchedCustomers = $segment->getMatchingCustomerIds();
        expect($matchedCustomers)->toBeArray();
    });

    test('can export and import complex nested conditions', function () {
        $complexConditions = [
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
                            'type' => 'customersegmentation/segment_condition_order_attributes',
                            'attribute' => 'grand_total',
                            'operator' => '>=',
                            'value' => '100',
                        ],
                    ],
                ],
                [
                    'type' => 'customersegmentation/segment_condition_customer_attributes',
                    'attribute' => 'group_id',
                    'operator' => '==',
                    'value' => '2',
                ],
            ],
        ];

        $segment = createComplexTestSegment('Export Import Test', $complexConditions);

        // Export conditions
        $conditionsModel = $segment->getConditions();
        $exportedArray = $conditionsModel->asArray();

        expect($exportedArray)->toBeArray();
        expect($exportedArray['type'])->toBe('customersegmentation/segment_condition_combine');
        expect($exportedArray['aggregator'])->toBe('any');
        expect(count($exportedArray['conditions']))->toBe(2);

        // Import conditions to new segment
        $newSegment = Mage::getModel('customersegmentation/segment');
        $newSegment->setName('Import Test Segment');
        $newSegment->setIsActive(1);

        $newConditionsModel = $newSegment->getConditions();
        $newConditionsModel->loadArray($exportedArray);

        $reimportedArray = $newConditionsModel->asArray();

        expect($reimportedArray['type'])->toBe($exportedArray['type']);
        expect($reimportedArray['aggregator'])->toBe($exportedArray['aggregator']);
        expect(count($reimportedArray['conditions']))->toBe(count($exportedArray['conditions']));
    });

    // Helper methods
    function createComplexTestCustomers(): void
    {
        $uniqueId = uniqid('complex_', true);
        $customers = [
            [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => "john.doe.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Jane',
                'lastname' => 'Smith',
                'email' => "jane.smith.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Bob',
                'lastname' => 'Johnson',
                'email' => "bob.johnson.{$uniqueId}@example.org",
                'group_id' => 2,
                'website_id' => 1,
            ],
            [
                'firstname' => 'Alice',
                'lastname' => 'Brown',
                'email' => "alice.brown.{$uniqueId}@test.com",
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

    function createComplexTestOrders(): void
    {
        $customerCollection = Mage::getModel('customer/customer')->getCollection();

        $orderData = [
            ['grand_total' => 75.50, 'status' => 'pending'],
            ['grand_total' => 650.00, 'status' => 'pending'],  // High value for CLV test
            ['grand_total' => 125.00, 'status' => 'processing'],
            ['grand_total' => 800.00, 'status' => 'pending'],  // High value for CLV test
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

    function createComplexTestCarts(): void
    {
        // Create basic cart data for testing
        // In practice, this would create quote records with items
    }

    function createComplexTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
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
        $segment->setDescription('Complex test segment for ' . $name);
        $segment->setIsActive(1);
        $segment->setWebsiteIds('1');
        $segment->setCustomerGroupIds('0,1,2,3');
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode($conditions));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus('pending');
        $segment->setPriority(10);
        $segment->save();

        return $segment;
    }
});
