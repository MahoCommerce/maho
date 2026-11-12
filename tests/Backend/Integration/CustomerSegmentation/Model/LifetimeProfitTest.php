<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Lifetime profit condition of a customer segment', function () {
    beforeEach(function () {
        $this->createProfitCustomer = function (array $orders): int {
            $customer = Mage::getModel('customer/customer')
                ->setFirstname('Lifetime')
                ->setLastname('Profit')
                ->setEmail('lifetime.profit.' . uniqid() . '@test.com')
                ->setGroupId(1)
                ->setWebsiteId(1);
            $customer->save();

            foreach ($orders as [$state, $grandTotal, $refunds]) {
                $order = Mage::getModel('sales/order')
                    ->setCustomerId($customer->getId())
                    ->setCustomerEmail($customer->getEmail())
                    ->setStoreId(1)
                    ->setGrandTotal($grandTotal)
                    ->setBaseGrandTotal($grandTotal)
                    ->setData('state', $state)
                    ->setStatus($state);
                $order->save();

                foreach ($refunds as $refund) {
                    Mage::getModel('sales/order_creditmemo')
                        ->setOrderId($order->getId())
                        ->setGrandTotal($refund)
                        ->setBaseGrandTotal($refund)
                        ->setState(Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED)
                        ->save();
                }
            }

            return (int) $customer->getId();
        };

        $this->profitSegment = function (string $operator, string $value): Maho_CustomerSegmentation_Model_Segment {
            $segment = Mage::getModel('customersegmentation/segment')
                ->setName('Lifetime profit ' . $operator . ' ' . $value)
                ->setIsActive()
                ->setWebsiteIds([1])
                ->setRefreshMode('manual')
                ->setConditionsSerialized(Mage::helper('core')->jsonEncode([
                    'type' => 'customersegmentation/segment_condition_combine',
                    'aggregator' => 'all',
                    'value' => 1,
                    'conditions' => [[
                        'type' => 'customersegmentation/segment_condition_customer_clv',
                        'attribute' => 'lifetime_profit',
                        'operator' => $operator,
                        'value' => $value,
                    ]],
                ]));
            $segment->save();
            return $segment;
        };
    });

    test('counts an order refunded in full as zero profit, not as a loss', function () {
        // 300 kept, plus an order of 100 refunded in full, which closes it: the profit is 300
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 300.00, []],
            [Mage_Sales_Model_Order::STATE_CLOSED, 100.00, [100.00]],
        ]);

        expect(($this->profitSegment)('>=', '300')->getMatchingCustomerIds(1))->toContain($customerId);
    });

    test('gives zero profit to a customer whose only order was refunded in full', function () {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_CLOSED, 100.00, [100.00]],
        ]);

        expect(($this->profitSegment)('==', '0')->getMatchingCustomerIds(1))->toContain($customerId);
    });

    test('subtracts a partial refund of an order that stays open', function () {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 300.00, [50.00]],
        ]);

        expect(($this->profitSegment)('>=', '250')->getMatchingCustomerIds(1))->toContain($customerId)
            ->and(($this->profitSegment)('>', '250')->getMatchingCustomerIds(1))->not->toContain($customerId);
    });

    test('leaves the canceled orders out of the profit', function () {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 100.00, []],
            [Mage_Sales_Model_Order::STATE_CANCELED, 200.00, []],
        ]);

        expect(($this->profitSegment)('>=', '100')->getMatchingCustomerIds(1))->toContain($customerId)
            ->and(($this->profitSegment)('>', '100')->getMatchingCustomerIds(1))->not->toContain($customerId);
    });

    test('leaves the refunds of a canceled order out of the profit', function () {
        $customerId = ($this->createProfitCustomer)([
            [Mage_Sales_Model_Order::STATE_COMPLETE, 100.00, []],
            [Mage_Sales_Model_Order::STATE_CANCELED, 100.00, [60.00]],
        ]);

        expect(($this->profitSegment)('>=', '100')->getMatchingCustomerIds(1))->toContain($customerId);
    });
});
