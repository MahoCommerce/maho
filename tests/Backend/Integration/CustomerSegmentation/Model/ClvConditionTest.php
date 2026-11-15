<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('CLV Condition Tests - Profit and Refunds Focus', function () {
    beforeEach(function () {
        $this->customers = createClvConditionTestData();
    });

    describe('Lifetime Profit Calculations', function () {
        test('matches the customers whose paid minus refunded amount passes the condition', function (string $operator, string $value, string $member) {
            $segment = createClvConditionTestSegment('Profit ' . $operator . ' ' . $value, [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_profit',
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member]);

            foreach ($matchedCustomers as $customerId) {
                $profit = calculateCustomerLifetimePaid($customerId) - calculateCustomerLifetimeRefunds($customerId);
                $operator === '>'
                    ? expect($profit)->toBeGreaterThan((float) $value)
                    : expect($profit)->toBeGreaterThanOrEqual((float) $value);
            }
        })->with([
            'at least 200' => ['>=', '200', 'High Profit'],
            'at least 100 with refunds' => ['>=', '100', 'Medium Profit'],
            'more than 0' => ['>', '0', 'High Profit'],
            'at least 99.99' => ['>=', '99.99', 'Medium Profit'],
        ]);

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
                $totalPaid = calculateCustomerLifetimePaid($customerId);
                $totalRefunds = calculateCustomerLifetimeRefunds($customerId);
                $profit = $totalPaid - $totalRefunds;

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
                $totalPaid = calculateCustomerLifetimePaid($customerId);
                $totalRefunds = calculateCustomerLifetimeRefunds($customerId);
                $profit = $totalPaid - $totalRefunds;

                expect($profit)->toBeLessThan(0.0);
            }
        });
    });

    describe('Lifetime Refunds Calculations', function () {
        test('matches the customers whose refunded credit memos pass the condition', function (string $operator, string $value, string $member) {
            $segment = createClvConditionTestSegment('Refunds ' . $operator . ' ' . $value, [
                'type' => 'customersegmentation/segment_condition_customer_clv',
                'attribute' => 'lifetime_refunds',
                'operator' => $operator,
                'value' => $value,
            ]);

            $matchedCustomers = $segment->getMatchingCustomerIds();
            expect($matchedCustomers)->toContain($this->customers[$member]);

            foreach ($matchedCustomers as $customerId) {
                $refunds = calculateCustomerLifetimeRefunds($customerId);
                $operator === '>'
                    ? expect($refunds)->toBeGreaterThan((float) $value)
                    : expect($refunds)->toBeGreaterThanOrEqual((float) $value);
            }
        })->with([
            'more than 0' => ['>', '0', 'Medium Profit'],
            'at least 25' => ['>=', '25', 'Big Spender'],
            'at least 50 across orders' => ['>=', '50', 'Multi Refunds'],
        ]);

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
            expect($matchedCustomers)->toContain($this->customers['High Profit']);

            foreach ($matchedCustomers as $customerId) {
                $orders = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']]);

                expect($orders->getSize())->toBeGreaterThan(0);

                $totalSales = 0.0;
                foreach ($orders as $order) {
                    $totalSales += (float) $order->getBaseGrandTotal();
                }
                expect($totalSales / $orders->getSize())->toBeGreaterThanOrEqual(75.0);
            }
        });
    });

    // Helper functions for test data creation and calculations
    /**
     * @return array<string, int> customer IDs keyed by "firstname lastname"
     */
    function createClvConditionTestData(): array
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

        $ids = [];
        foreach ($customers as $customerData) {
            $customer = Mage::getModel('customer/customer');
            $customer->setFirstname($customerData['firstname']);
            $customer->setLastname($customerData['lastname']);
            $customer->setEmail($customerData['email']);
            $customer->setGroupId($customerData['group_id']);
            $customer->setWebsiteId($customerData['website_id']);
            $customer->save();
            $ids[$customerData['firstname'] . ' ' . $customerData['lastname']] = (int) $customer->getId();

            // Create orders and credit memos
            foreach ($customerData['orders'] as $orderData) {
                $order = Mage::getModel('sales/order');
                $order->setCustomerId($customer->getId());
                $order->setCustomerEmail($customer->getEmail());
                $order->setGrandTotal($orderData['total']);
                $order->setBaseGrandTotal($orderData['total']);
                $order->setStoreId(1);
                $order->setCreatedAt(date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days')));

                $order->setData('state', orderStateForStatus($orderData['status']));
                $order->setStatus($orderData['status']);

                $order->save();

                if ($orderData['status'] !== 'canceled') {
                    Mage::getModel('sales/order_invoice')
                        ->setOrderId($order->getId())
                        ->setStoreId(1)
                        ->setGrandTotal($orderData['total'])
                        ->setBaseGrandTotal($orderData['total'])
                        ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                        ->save();
                }

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

        return $ids;
    }

    function calculateCustomerLifetimeSales(int|string $customerId): float
    {
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', (int) $customerId)
            ->addFieldToFilter('state', ['nin' => ['canceled', 'closed']]);

        $total = 0.0;
        foreach ($orders as $order) {
            $total += (float) $order->getBaseGrandTotal();
        }

        return $total;
    }

    function calculateCustomerLifetimePaid(int|string $customerId): float
    {
        $orderIds = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', (int) $customerId)
            ->getAllIds();
        if ($orderIds === []) {
            return 0.0;
        }

        $invoices = Mage::getResourceModel('sales/order_invoice_collection')
            ->addFieldToFilter('order_id', ['in' => $orderIds])
            ->addFieldToFilter('state', Mage_Sales_Model_Order_Invoice::STATE_PAID);

        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) $invoice->getBaseGrandTotal();
        }

        return $total;
    }

    function calculateCustomerLifetimeRefunds(int|string $customerId): float
    {
        $orderIds = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', (int) $customerId)
            ->getAllIds();
        if ($orderIds === []) {
            return 0.0;
        }

        $creditmemos = Mage::getResourceModel('sales/order_creditmemo_collection')
            ->addFieldToFilter('order_id', ['in' => $orderIds])
            ->addFieldToFilter('state', Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED);

        $total = 0.0;
        foreach ($creditmemos as $creditmemo) {
            $total += (float) $creditmemo->getBaseGrandTotal();
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
