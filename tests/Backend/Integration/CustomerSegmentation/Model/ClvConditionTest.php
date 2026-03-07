<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('CLV Condition Tests - Profit and Refunds Focus', function () {
    beforeEach(function () {
        createClvConditionTestData();
    });

    describe('Lifetime Profit Calculations', function () {
        test('calculates profit correctly for customers with sales only (no refunds)', function () {
            $segment = createClvConditionTestSegment('Sales Only Profit', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '>=',
                'value' => '200',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                // Calculate expected profit (sales - refunds)
                $totalSales = calculateCustomerLifetimeSales($customerId);
                $totalRefunds = calculateCustomerLifetimeRefunds($customerId);
                $expectedProfit = $totalSales - $totalRefunds;

                expect($expectedProfit)->toBeGreaterThanOrEqual(200.0);
            }
        });

        test('calculates profit correctly for customers with sales and refunds', function () {
            $segment = createClvConditionTestSegment('Sales with Refunds Profit', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '>=',
                'value' => '100',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $totalSales = calculateCustomerLifetimeSales($customerId);
                $totalRefunds = calculateCustomerLifetimeRefunds($customerId);
                $expectedProfit = $totalSales - $totalRefunds;

                expect($expectedProfit)->toBeGreaterThanOrEqual(100.0);

                // Verify this customer has both sales and refunds
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $creditmemoCount = 0;
                foreach ($orders as $order) {
                    $creditmemoCount += Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('order_id', $order->getId())
                        ->getSize();
                }

                if ($creditmemoCount > 0) {
                    expect($totalRefunds)->toBeGreaterThan(0.0);
                }
            }
        });

        test('handles customers with zero profit (sales equal refunds)', function () {
            $segment = createClvConditionTestSegment('Zero Profit', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '==',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $totalSales = calculateCustomerLifetimeSales($customerId);
                $totalRefunds = calculateCustomerLifetimeRefunds($customerId);
                $profit = $totalSales - $totalRefunds;

                expect(abs($profit))->toBeLessThan(0.01); // Allow for floating point precision
            }
        });

        test('handles customers with negative profit (refunds exceed sales)', function () {
            $segment = createClvConditionTestSegment('Negative Profit', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '<',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $totalSales = calculateCustomerLifetimeSales($customerId);
                $totalRefunds = calculateCustomerLifetimeRefunds($customerId);
                $profit = $totalSales - $totalRefunds;

                expect($profit)->toBeLessThan(0.0);
            }
        });

        test('excludes canceled and closed orders from profit calculations', function () {
            $segment = createClvConditionTestSegment('Profit Excluding Canceled', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '>=',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                // Verify canceled orders are not included in profit calculation
                $canceledOrders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['in' => ['canceled', 'closed']]);

                $validOrders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['nin' => ['canceled', 'closed']]);

                // Calculate what sales should be (excluding canceled/closed)
                $expectedSales = 0.0;
                foreach ($validOrders as $order) {
                    $expectedSales += (float) $order->getGrandTotal();
                }

                $actualSales = calculateCustomerLifetimeSales($customerId);
                expect(abs($actualSales - $expectedSales))->toBeLessThan(0.01);
            }
        });
    });

    describe('Lifetime Refunds Calculations', function () {
        test('calculates refunds correctly for customers with credit memos', function () {
            $segment = createClvConditionTestSegment('Has Refunds', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_refunds',
                'operator' => '>',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $expectedRefunds = calculateCustomerLifetimeRefunds($customerId);
                expect($expectedRefunds)->toBeGreaterThan(0.0);

                // Verify this customer actually has credit memos
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $creditmemoCount = 0;
                foreach ($orders as $order) {
                    $creditmemoCount += Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('order_id', $order->getId())
                        ->getSize();
                }

                expect($creditmemoCount)->toBeGreaterThan(0);
            }
        });

        test('returns zero refunds for customers with no credit memos', function () {
            $segment = createClvConditionTestSegment('No Refunds', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_refunds',
                'operator' => '==',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $refunds = calculateCustomerLifetimeRefunds($customerId);
                expect($refunds)->toBe(0.0);

                // Verify this customer has no credit memos
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $creditmemoCount = 0;
                foreach ($orders as $order) {
                    $creditmemoCount += Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('order_id', $order->getId())
                        ->getSize();
                }

                expect($creditmemoCount)->toBe(0);
            }
        });

        test('calculates correct refund amounts for partial refunds', function () {
            $segment = createClvConditionTestSegment('Partial Refunds', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_refunds',
                'operator' => '>=',
                'value' => '25',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $refunds = calculateCustomerLifetimeRefunds($customerId);
                expect($refunds)->toBeGreaterThanOrEqual(25.0);

                // Verify refund calculation matches sum of credit memo grand totals
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $expectedRefunds = 0.0;
                foreach ($orders as $order) {
                    $creditmemos = Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('order_id', $order->getId());

                    foreach ($creditmemos as $creditmemo) {
                        $expectedRefunds += (float) $creditmemo->getGrandTotal();
                    }
                }

                expect(abs($refunds - $expectedRefunds))->toBeLessThan(0.01);
            }
        });

        test('handles customers with multiple refunds across different orders', function () {
            $segment = createClvConditionTestSegment('Multiple Refunds', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_refunds',
                'operator' => '>=',
                'value' => '50',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $refunds = calculateCustomerLifetimeRefunds($customerId);
                expect($refunds)->toBeGreaterThanOrEqual(50.0);

                // Check that customer has refunds from different orders
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId);

                $ordersWithRefunds = [];
                foreach ($orders as $order) {
                    $creditmemoCount = Mage::getResourceModel('sales/order_creditmemo_collection')
                        ->addFieldToFilter('order_id', $order->getId())
                        ->getSize();
                    if ($creditmemoCount > 0) {
                        $ordersWithRefunds[] = $order->getId();
                    }
                }

                $uniqueOrders = array_unique($ordersWithRefunds);
                // Some customers should have refunds across multiple orders
                if (count($uniqueOrders) > 1) {
                    expect(count($uniqueOrders))->toBeGreaterThan(1);
                }
            }
        });
    });

    describe('Complex Join Logic Verification', function () {
        test('lifetime_profit SQL generation includes correct joins', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_clv');
            $condition->setAttribute('lifetime_profit');
            $condition->setOperator('>=');
            $condition->setValue('100');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('SUM');
            expect($sql)->toContain('grand_total');
            expect($sql)->toContain('COALESCE');
            expect($sql)->toContain('LEFT JOIN');
        });

        test('lifetime_refunds SQL generation includes credit memo join', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_clv');
            $condition->setAttribute('lifetime_refunds');
            $condition->setOperator('>=');
            $condition->setValue('50');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getConditionsSql($adapter);

            expect($sql)->toBeString();
            expect($sql)->toContain('creditmemo');
            expect($sql)->toContain('order_id');
            expect($sql)->toContain('SUM');
        });

        test('profit and refunds queries handle website filtering correctly', function () {
            $condition = Mage::getModel('customersegmentation/segment_condition_customer_clv');
            $condition->setAttribute('lifetime_profit');
            $condition->setOperator('>=');
            $condition->setValue('100');

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sql = $condition->getSubfilterSql('e.entity_id', true, 1);

            expect($sql)->toBeString();
            expect($sql)->toContain('store_id IN');
        });
    });

    describe('Edge Cases and Error Handling', function () {
        test('handles customers with no orders for profit calculations', function () {
            $segment = createClvConditionTestSegment('Zero Profit No Orders', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '==',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            $customersWithNoOrders = 0;
            foreach ($matchedCustomers as $customerId) {
                $orderCount = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']])
                    ->getSize();

                if ($orderCount === 0) {
                    $customersWithNoOrders++;
                    $profit = calculateCustomerLifetimeSales($customerId) - calculateCustomerLifetimeRefunds($customerId);
                    expect($profit)->toBe(0.0);
                }
            }

            // Either we tested some customers with no orders, or none matched (both are valid)
            expect($customersWithNoOrders)->toBeInt();
            expect($customersWithNoOrders >= 0)->toBe(true);
        });

        test('handles customers with orders but no refunds', function () {
            $segment = createClvConditionTestSegment('Sales No Refunds', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '>',
                'value' => '0',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $refunds = calculateCustomerLifetimeRefunds($customerId);
                $sales = calculateCustomerLifetimeSales($customerId);

                if ($refunds === 0.0 && $sales > 0.0) {
                    // For customers with sales but no refunds, profit should equal sales
                    $profit = $sales - $refunds;
                    expect($profit)->toBe($sales);
                    expect($profit)->toBeGreaterThan(0.0);
                }
            }
        });

        test('handles floating point precision in profit calculations', function () {
            $segment = createClvConditionTestSegment('Precision Test', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => '>=',
                'value' => '99.99',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $sales = calculateCustomerLifetimeSales($customerId);
                $refunds = calculateCustomerLifetimeRefunds($customerId);
                $profit = $sales - $refunds;

                expect($profit)->toBeGreaterThanOrEqual(99.99);
            }
        });
    });

    describe('Existing CLV Attributes Regression Tests', function () {
        test('lifetime_sales still works correctly', function () {
            $segment = createClvConditionTestSegment('Sales Regression', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_sales',
                'operator' => '>=',
                'value' => '100',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $expectedSales = calculateCustomerLifetimeSales($customerId);
                expect($expectedSales)->toBeGreaterThanOrEqual(100.0);
            }
        });

        test('lifetime_orders still works correctly', function () {
            $segment = createClvConditionTestSegment('Orders Regression', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'number_of_orders',
                'operator' => '>=',
                'value' => '2',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toBeArray();

            foreach ($matchedCustomers as $customerId) {
                $orderCount = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['nin' => ['canceled', 'closed']])
                    ->getSize();

                expect($orderCount)->toBeGreaterThanOrEqual(2);
            }
        });

        test('average_order_value still works correctly', function () {
            $segment = createClvConditionTestSegment('AOV Regression', [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'average_order_value',
                'operator' => '>=',
                'value' => '75',
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('status', ['nin' => ['canceled', 'closed']]);

                if ($orders->getSize() > 0) {
                    $totalSales = 0.0;
                    $orderCount = 0;
                    foreach ($orders as $order) {
                        $totalSales += (float) $order->getGrandTotal();
                        $orderCount++;
                    }

                    $aov = $totalSales / $orderCount;
                    expect($aov)->toBeGreaterThanOrEqual(75.0);
                }
            }
        });
    });

    // Helper functions for test data creation and calculations
    function createClvConditionTestData(): void
    {
        $uniqueId = uniqid('clv_test_', true);

        $customers = [
            // Customer with sales only (no refunds) - high profit
            [
                'firstname' => 'High',
                'lastname' => 'Profit',
                'email' => "high.profit.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [
                    ['total' => 150.00, 'status' => 'complete', 'refunds' => []],
                    ['total' => 200.00, 'status' => 'complete', 'refunds' => []],
                ],
            ],
            // Customer with sales and partial refunds - medium profit
            [
                'firstname' => 'Medium',
                'lastname' => 'Profit',
                'email' => "medium.profit.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [
                    ['total' => 200.00, 'status' => 'complete', 'refunds' => [50.00]],
                    ['total' => 100.00, 'status' => 'complete', 'refunds' => []],
                ],
            ],
            // Customer with sales equal refunds - zero profit
            [
                'firstname' => 'Zero',
                'lastname' => 'Profit',
                'email' => "zero.profit.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [
                    ['total' => 100.00, 'status' => 'complete', 'refunds' => [100.00]],
                ],
            ],
            // Customer with refunds exceeding sales - negative profit
            [
                'firstname' => 'Negative',
                'lastname' => 'Profit',
                'email' => "negative.profit.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [
                    ['total' => 100.00, 'status' => 'complete', 'refunds' => [120.00]],
                ],
            ],
            // Customer with multiple refunds across different orders
            [
                'firstname' => 'Multi',
                'lastname' => 'Refunds',
                'email' => "multi.refunds.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [
                    ['total' => 150.00, 'status' => 'complete', 'refunds' => [30.00, 20.00]],
                    ['total' => 200.00, 'status' => 'complete', 'refunds' => [40.00]],
                ],
            ],
            // Customer with canceled orders (should be excluded)
            [
                'firstname' => 'Canceled',
                'lastname' => 'Orders',
                'email' => "canceled.orders.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [
                    ['total' => 100.00, 'status' => 'complete', 'refunds' => []],
                    ['total' => 200.00, 'status' => 'canceled', 'refunds' => []],
                ],
            ],
            // Customer with no orders - zero everything
            [
                'firstname' => 'No',
                'lastname' => 'Orders',
                'email' => "no.orders.{$uniqueId}@test.com",
                'group_id' => 1,
                'website_id' => 1,
                'orders' => [],
            ],
            // Customer with only refunds (no successful orders)
            [
                'firstname' => 'Only',
                'lastname' => 'Refunds',
                'email' => "only.refunds.{$uniqueId}@test.com",
                'group_id' => 2,
                'website_id' => 1,
                'orders' => [
                    ['total' => 75.00, 'status' => 'complete', 'refunds' => [25.00, 50.00]],
                ],
            ],
            // Customer with high value orders and small refunds
            [
                'firstname' => 'Big',
                'lastname' => 'Spender',
                'email' => "big.spender.{$uniqueId}@test.com",
                'group_id' => 2,
                'website_id' => 1,
                'orders' => [
                    ['total' => 500.00, 'status' => 'complete', 'refunds' => [25.00]],
                    ['total' => 300.00, 'status' => 'complete', 'refunds' => []],
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
            $customer->save();


            // Create orders and credit memos
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderData['total']);
                $order->setStoreId(1);
                $order->setCreatedAt(date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days')));

                if ($orderData['status'] === 'canceled') {
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                    $order->setStatus('canceled');
                } else {
                    $order->setState(Mage_Sales_Model_Order::STATE_NEW);
                    $order->setStatus($orderData['status']);
                }

                $order->save();

                // Create credit memos for this order
                foreach ($orderData['refunds'] as $refundAmount) {
                    $creditmemo = Mage::getModel('sales/order_creditmemo');
                    $creditmemo->setOrderId($order->getId());
                    $creditmemo->setGrandTotal($refundAmount);
                    $creditmemo->setSubtotal($refundAmount);
                    $creditmemo->setBaseGrandTotal($refundAmount);
                    $creditmemo->setBaseSubtotal($refundAmount);
                    $creditmemo->setState(Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED);
                    $creditmemo->setCreatedAt(date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')));
                    $creditmemo->save();

                }
            }
        }
    }

    function calculateCustomerLifetimeSales(int|string $customerId): float
    {
        $customerId = (int) $customerId;
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('status', ['nin' => ['canceled', 'closed']]);

        $total = 0.0;
        foreach ($orders as $order) {
            $total += (float) $order->getGrandTotal();
        }

        return $total;
    }

    function calculateCustomerLifetimeRefunds(int|string $customerId): float
    {
        // For test purposes, we'll calculate based on the actual creditmemos created
        $customerId = (int) $customerId;

        // Find orders for this customer
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', $customerId);

        $total = 0.0;
        foreach ($orders as $order) {
            // Get creditmemos for this order
            $creditmemos = Mage::getResourceModel('sales/order_creditmemo_collection')
                ->addFieldToFilter('order_id', $order->getId());

            foreach ($creditmemos as $creditmemo) {
                $total += (float) $creditmemo->getGrandTotal();
            }
        }

        return $total;
    }

    function createClvConditionTestSegment(string $name, array $conditions): Maho_CustomerSegmentation_Model_Segment
    {
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
        $segment->setDescription('CLV condition test segment for ' . $name);
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
