<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Lifetime profit and lifetime refunds conditions of a customer segment', function () {
    beforeEach(function () {
        // Each order: [order state, grand total, invoices as [amount, state], credit memos as [amount, state]]
        $this->createProfitCustomer = function (array $orders): int {
            $customer = Mage::getModel('customer/customer')
                ->setFirstname('Lifetime')
                ->setLastname('Profit')
                ->setEmail('lifetime.profit.' . uniqid() . '@test.com')
                ->setGroupId(1)
                ->setWebsiteId(1);
            $customer->save();

            foreach ($orders as [$state, $grandTotal, $invoices, $creditmemos]) {
                $order = Mage::getModel('sales/order')
                    ->setCustomerId($customer->getId())
                    ->setCustomerEmail($customer->getEmail())
                    ->setStoreId(1)
                    ->setGrandTotal($grandTotal)
                    ->setBaseGrandTotal($grandTotal)
                    ->setData('state', $state)
                    ->setStatus($state);
                $order->save();

                foreach ($invoices as [$amount, $invoiceState]) {
                    Mage::getModel('sales/order_invoice')
                        ->setOrderId($order->getId())
                        ->setStoreId(1)
                        ->setGrandTotal($amount)
                        ->setBaseGrandTotal($amount)
                        ->setState($invoiceState)
                        ->save();
                }

                foreach ($creditmemos as [$amount, $creditmemoState]) {
                    Mage::getModel('sales/order_creditmemo')
                        ->setOrderId($order->getId())
                        ->setStoreId(1)
                        ->setGrandTotal($amount)
                        ->setBaseGrandTotal($amount)
                        ->setState($creditmemoState)
                        ->save();
                }
            }

            return (int) $customer->getId();
        };

        $this->clvSegment = function (string $attribute, string $operator, string $value): Maho_CustomerSegmentation_Model_Segment {
            $segment = Mage::getModel('customersegmentation/segment')
                ->setName($attribute . ' ' . $operator . ' ' . $value)
                ->setIsActive()
                ->setWebsiteIds([1])
                ->setRefreshMode('manual')
                ->setConditionsSerialized(Mage::helper('core')->jsonEncode([
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'all',
                    'value' => 1,
                    'conditions' => [[
                        'type' => 'customersegmentation/segment_condition_customer_clv',
                        'attribute' => $attribute,
                        'operator' => $operator,
                        'value' => $value,
                    ]],
                ]));
            $segment->save();
            return $segment;
        };

        $this->expectProfit = function (int $customerId, string $profit): void {
            expect(($this->clvSegment)('lifetime_profit', '==', $profit)->getMatchingCustomerIds(1))->toContain($customerId);
        };
    });

    $paid = Mage_Sales_Model_Order_Invoice::STATE_PAID;
    $refunded = Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED;

    test('counts an order refunded in full as zero profit, not as a loss', function () use ($paid, $refunded) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 300.00, [[300.00, $paid]], []],
            [Mage_Sales_Model_Order::STATE_CLOSED, 100.00, [[100.00, $paid]], [[100.00, $refunded]]],
        ]);

        ($this->expectProfit)($customerId, '300');
    });

    test('gives zero profit to a customer whose only order was refunded in full', function () use ($paid, $refunded) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_CLOSED, 100.00, [[100.00, $paid]], [[100.00, $refunded]]],
        ]);

        ($this->expectProfit)($customerId, '0');
    });

    test('subtracts a partial refund of an order that stays open', function () use ($paid, $refunded) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 300.00, [[300.00, $paid]], [[50.00, $refunded]]],
        ]);

        ($this->expectProfit)($customerId, '250');
    });

    test('adds nothing for a canceled order that was never paid', function () use ($paid) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 100.00, [[100.00, $paid]], []],
            [Mage_Sales_Model_Order::STATE_CANCELED, 200.00, [], []],
        ]);

        ($this->expectProfit)($customerId, '100');
    });

    test('keeps what was paid and not refunded on a canceled order', function () use ($paid, $refunded) {
        // One item paid 100 and refunded 60, the other item canceled before payment
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_CANCELED, 150.00, [[100.00, $paid]], [[60.00, $refunded]]],
        ]);

        ($this->expectProfit)($customerId, '40');
    });

    test('adds only the paid part of an order whose other items were canceled', function () use ($paid, $refunded) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_CLOSED, 150.00, [[100.00, $paid]], [[100.00, $refunded]]],
        ]);

        ($this->expectProfit)($customerId, '0');
    });

    test('gives zero profit to a customer whose orders are not paid', function () {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, 100.00, [], []],
            [Mage_Sales_Model_Order::STATE_PROCESSING, 80.00, [[80.00, Mage_Sales_Model_Order_Invoice::STATE_OPEN]], []],
        ]);

        ($this->expectProfit)($customerId, '0');
    });

    test('leaves canceled invoices and credit memos that returned no money out of the profit', function () use ($paid) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 100.00, [
                [100.00, Mage_Sales_Model_Order_Invoice::STATE_CANCELED],
                [100.00, $paid],
            ], [
                [30.00, Mage_Sales_Model_Order_Creditmemo::STATE_OPEN],
                [40.00, Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED],
            ]],
        ]);

        ($this->expectProfit)($customerId, '100');
    });

    test('counts only the refunded credit memos in the lifetime refunds', function () use ($paid, $refunded) {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 100.00, [[100.00, $paid]], [
                [20.00, $refunded],
                [30.00, Mage_Sales_Model_Order_Creditmemo::STATE_OPEN],
                [40.00, Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED],
            ]],
        ]);

        expect(($this->clvSegment)('lifetime_refunds', '==', '20')->getMatchingCustomerIds(1))->toContain($customerId);
    });
});
