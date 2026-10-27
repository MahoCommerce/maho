<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Free shipping of a cart rule on a quote with billing and shipping addresses', function () {
    beforeEach(function () {
        $this->product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('price', ['gt' => 0])
            ->addAttributeToFilter('weight', ['gt' => 0])
            ->addWebsiteFilter(1)
            ->joinField('qty', 'cataloginventory/stock_item', 'qty', 'product_id=entity_id', '{{table}}.is_in_stock=1 AND {{table}}.qty>5', 'inner')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$this->product->getId()) {
            $this->markTestSkipped('No simple product in stock available for testing');
        }
        $this->product = Mage::getModel('catalog/product')->setStoreId(1)->load($this->product->getId());
        $this->rule = null;
        $this->quote = null;
    });

    afterEach(function () {
        $this->quote?->delete();
        $this->rule?->delete();
    });

    test('applies to the shipping address', function (int $freeShipping, bool $withSubtotalCondition) {
        $conditions = [
            '1' => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        ];
        if ($withSubtotalCondition) {
            $conditions['1--1'] = [
                'type' => 'salesrule/rule_condition_address',
                'attribute' => 'base_subtotal',
                'operator' => '>=',
                'value' => '1',
            ];
        }
        $this->rule = Mage::getModel('salesrule/rule')->loadPost([
            'name' => 'Free shipping address test',
            'is_active' => 1,
            'coupon_type' => Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON,
            'website_ids' => [1],
            'customer_group_ids' => [Mage_Customer_Model_Group::NOT_LOGGED_IN_ID],
            'simple_action' => Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
            'discount_amount' => 0,
            'simple_free_shipping' => $freeShipping,
            'conditions' => $conditions,
        ])->save();

        $quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerIsGuest(true);
        $addressData = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $quote->getBillingAddress()->addData($addressData);
        $quote->getShippingAddress()->addData($addressData);
        $quote->addProduct($this->product, 1);
        $quote->collectTotals();

        $item = $quote->getAllVisibleItems()[0];
        if ($freeShipping === Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS) {
            expect($quote->getShippingAddress()->getFreeShipping())->toBeTrue();
            expect($quote->getBillingAddress()->getFreeShipping())->toBeFalse();
        } else {
            expect($item->getFreeShipping())->toBeTruthy();
        }
    })->with([
        'for the shipment, without conditions' => [Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS, false],
        'for the shipment, with a subtotal condition' => [Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS, true],
        'for matching items, with a subtotal condition' => [Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM, true],
    ]);

    test('validates a weight condition on the weight of the current cart', function (int $discount, int $freeShipping) {
        $weight = (float) $this->product->getWeight();
        $this->rule = Mage::getModel('salesrule/rule')->loadPost([
            'name' => 'Weight condition test',
            'is_active' => 1,
            'coupon_type' => Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON,
            'website_ids' => [1],
            'customer_group_ids' => [Mage_Customer_Model_Group::NOT_LOGGED_IN_ID],
            'simple_action' => Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
            'discount_amount' => $discount,
            'simple_free_shipping' => $freeShipping,
            'conditions' => [
                '1' => ['type' => 'salesrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
                '1--1' => [
                    'type' => 'salesrule/rule_condition_address',
                    'attribute' => 'weight',
                    'operator' => '>=',
                    'value' => (string) ($weight * 1.5),
                ],
            ],
        ])->save();

        $this->quote = Mage::getModel('sales/quote')->setStoreId(1)->setCustomerIsGuest(true);
        $addressData = ['country_id' => 'US', 'region_id' => 12, 'postcode' => '90210'];
        $this->quote->getBillingAddress()->addData($addressData);
        $this->quote->getShippingAddress()->addData($addressData);
        $this->quote->addProduct($this->product, 1);
        $this->quote->collectTotals()->save();
        expect((float) $this->quote->getShippingAddress()->getDiscountAmount())->toBe(0.0);
        expect($this->quote->getShippingAddress()->getFreeShipping())->toBeFalse();

        new ReflectionProperty(Mage_SalesRule_Model_Validator::class, '_rules')
            ->setValue(Mage::getSingleton('salesrule/validator'), null);
        $this->quote = Mage::getModel('sales/quote')->setStoreId(1)->load($this->quote->getId());

        $this->quote->getAllVisibleItems()[0]->setQty(2);
        $this->quote->setTotalsCollectedFlag(false)->collectTotals();

        $address = $this->quote->getShippingAddress();
        expect((float) $address->getWeight())->toBe($weight * 2);
        if ($discount) {
            expect((float) $address->getDiscountAmount())->toBeLessThan(0.0);
        } else {
            expect($address->getFreeShipping())->toBeTrue();
        }
    })->with([
        'a discount does not reuse a rule check made with the old weight' => [10, 0],
        'free shipping for the shipment' => [0, Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS],
    ]);
});
