<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Amount conditions of a customer segment', function () {
    beforeEach(function () {
        $customer = Mage::getModel('customer/customer')
            ->setFirstname('Two')
            ->setLastname('Currencies')
            ->setEmail('two.currencies.' . uniqid() . '@test.com')
            ->setGroupId(1)
            ->setWebsiteId(1);
        $customer->save();
        $this->customerId = (int) $customer->getId();
        $this->baseCurrency = Mage::app()->getWebsite(1)->getBaseCurrencyCode();

        // The order currency amounts add up to 980 and the base currency amounts to 1100
        foreach ([['EUR', 900.00, 1000.00], ['GBP', 80.00, 100.00]] as [$orderCurrency, $grandTotal, $baseGrandTotal]) {
            Mage::getModel('sales/order')
                ->setCustomerId($this->customerId)
                ->setCustomerEmail($customer->getEmail())
                ->setStoreId(1)
                ->setOrderCurrencyCode($orderCurrency)
                ->setBaseCurrencyCode($this->baseCurrency)
                ->setGrandTotal($grandTotal)
                ->setBaseGrandTotal($baseGrandTotal)
                ->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)
                ->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE)
                ->save();
        }
    });

    function baseCurrencySegment(string $type, string $attribute, string $value): Maho_CustomerSegmentation_Model_Segment
    {
        $segment = Mage::getModel('customersegmentation/segment')
            ->setName('Base currency ' . $attribute)
            ->setIsActive()
            ->setWebsiteIds([1])
            ->setRefreshMode('manual')
            ->setConditionsSerialized(Mage::helper('core')->jsonEncode([
                'type' => 'customersegmentation/segment_condition_combine',
                'aggregator' => 'all',
                'value' => 1,
                'conditions' => [['type' => $type, 'attribute' => $attribute, 'operator' => '>=', 'value' => $value]],
            ]));
        $segment->save();
        return $segment;
    }

    test('compares the base currency amounts of the orders', function (string $type, string $attribute, string $value) {
        $segment = baseCurrencySegment($type, $attribute, $value);

        expect($segment->getMatchingCustomerIds(1))->toContain($this->customerId);
    })->with([
        'lifetime sales' => ['customersegmentation/segment_condition_customer_clv', 'lifetime_sales', '1050'],
        'average order value' => ['customersegmentation/segment_condition_customer_clv', 'average_order_value', '520'],
        'lifetime profit' => ['customersegmentation/segment_condition_customer_clv', 'lifetime_profit', '1050'],
        'total ordered amount' => ['customersegmentation/segment_condition_order_attributes', 'total_ordered_amount', '1050'],
        'average order amount' => ['customersegmentation/segment_condition_order_attributes', 'average_order_amount', '520'],
        'grand total' => ['customersegmentation/segment_condition_order_attributes', 'grand_total', '950'],
    ]);

    test('counts the orders of another website that uses the same base currency', function () {
        $otherStoreId = null;
        foreach (Mage::app()->getWebsites() as $website) {
            if ((int) $website->getId() !== 1 && $website->getBaseCurrencyCode() === $this->baseCurrency && $website->getStoreIds() !== []) {
                $otherStoreId = (int) array_values($website->getStoreIds())[0];
                break;
            }
        }
        if ($otherStoreId === null) {
            $this->markTestSkipped('The test store has no second website with the same base currency');
        }

        Mage::getModel('sales/order')
            ->setCustomerId($this->customerId)
            ->setStoreId($otherStoreId)
            ->setBaseCurrencyCode($this->baseCurrency)
            ->setGrandTotal(2000.00)
            ->setBaseGrandTotal(2000.00)
            ->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)
            ->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE)
            ->save();
        $segment = baseCurrencySegment('customersegmentation/segment_condition_customer_clv', 'lifetime_sales', '3000');

        expect($segment->getMatchingCustomerIds())->toContain($this->customerId);
    });

    test('shows the base currency code after the amount in the label', function () {
        $segment = baseCurrencySegment('customersegmentation/segment_condition_customer_clv', 'lifetime_sales', '1050');
        $leaf = $segment->getConditions()->getConditions()[0];

        expect($leaf->asString())->toEndWith('1050 ' . $this->baseCurrency);
    });

    test('shows no currency code after a count', function () {
        $segment = baseCurrencySegment('customersegmentation/segment_condition_customer_clv', 'number_of_orders', '2');
        $leaf = $segment->getConditions()->getConditions()[0];

        expect($leaf->asString())->toEndWith(' 2');
    });
});
