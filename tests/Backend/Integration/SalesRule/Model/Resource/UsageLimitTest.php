<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function usageLimitCustomer(#[\SensitiveParameter] string $email): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1)
        ->setEmail($email)
        ->setFirstname('Usage')
        ->setLastname('Limit')
        ->setGroupId(1)
        ->save();
    return $customer;
}

function usageLimitRule(int $usesPerCustomer = 0): Mage_SalesRule_Model_Rule
{
    $rule = Mage::getModel('salesrule/rule');
    $rule->setName('Usage limit ' . uniqid())
        ->setIsActive(1)
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1])
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(10)
        ->setUsesPerCustomer($usesPerCustomer)
        ->save();
    return $rule;
}

function usageLimitCoupon(Mage_SalesRule_Model_Rule $rule, ?int $usageLimit, ?int $usagePerCustomer = null): Mage_SalesRule_Model_Coupon
{
    $coupon = Mage::getModel('salesrule/coupon');
    $coupon->setRuleId($rule->getId())
        ->setCode('LIMIT-' . strtoupper(uniqid()))
        ->setUsageLimit($usageLimit)
        ->setUsagePerCustomer($usagePerCustomer)
        ->save();
    return $coupon;
}

function usageLimitOrder(Mage_SalesRule_Model_Rule $rule, Mage_SalesRule_Model_Coupon $coupon, ?int $customerId): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setCustomerId($customerId)
        ->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_NEW)
        ->setStatus('pending')
        ->setAppliedRuleIds((string) $rule->getId())
        ->setCouponCode($coupon->getCode())
        ->setDiscountAmount(10)
        ->setBaseDiscountAmount(10)
        ->setGrandTotal(90)
        ->setBaseGrandTotal(90);
    $item = Mage::getModel('sales/order_item');
    $item->setProductType('simple')
        ->setSku('usage-limit-item')
        ->setName('Usage limit item')
        ->setQtyOrdered(1)
        ->setPrice(100)
        ->setBasePrice(100)
        ->setRowTotal(100)
        ->setBaseRowTotal(100);
    $order->addItem($item);
    $order->save();
    return $order;
}

describe('coupon usage counters', function () {
    test('coupon increment stops at the usage limit', function () {
        $coupon = usageLimitCoupon(usageLimitRule(), 2);
        $resource = Mage::getResourceModel('salesrule/coupon');

        $resource->incrementTimesUsed((int) $coupon->getId());
        $resource->incrementTimesUsed((int) $coupon->getId());
        $resource->incrementTimesUsed((int) $coupon->getId());

        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(2);
    });

    test('coupon increment is unlimited without a usage limit', function () {
        $coupon = usageLimitCoupon(usageLimitRule(), null);
        $resource = Mage::getResourceModel('salesrule/coupon');

        for ($i = 0; $i < 3; $i++) {
            $resource->incrementTimesUsed((int) $coupon->getId());
        }

        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(3);
    });

    test('coupon decrement never goes below zero', function () {
        $coupon = usageLimitCoupon(usageLimitRule(), 1);
        $resource = Mage::getResourceModel('salesrule/coupon');

        $resource->incrementTimesUsed((int) $coupon->getId());
        $resource->decrementTimesUsed((int) $coupon->getId());
        $resource->decrementTimesUsed((int) $coupon->getId());

        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(0);
    });

    test('per-customer coupon counter never exceeds the per-customer limit', function () {
        $customer = usageLimitCustomer('coupon-per-customer@example.com');
        $coupon = usageLimitCoupon(usageLimitRule(), null, 1);
        $customerId = (int) $customer->getId();
        $couponId = (int) $coupon->getId();
        $resource = Mage::getResourceModel('salesrule/coupon_usage');

        $resource->incrementCustomerTimesUsed($customerId, $couponId, 1);
        $resource->incrementCustomerTimesUsed($customerId, $couponId, 1);

        $usage = new \Maho\DataObject();
        $resource->loadByCustomerCoupon($usage, $customerId, $couponId);
        expect((int) $usage->getTimesUsed())->toBe(1);
    });

    test('per-customer coupon counter is unlimited without a per-customer limit', function () {
        $customer = usageLimitCustomer('coupon-per-customer-unlimited@example.com');
        $coupon = usageLimitCoupon(usageLimitRule(), null);
        $customerId = (int) $customer->getId();
        $couponId = (int) $coupon->getId();
        $resource = Mage::getResourceModel('salesrule/coupon_usage');

        $resource->incrementCustomerTimesUsed($customerId, $couponId, 0);
        $resource->incrementCustomerTimesUsed($customerId, $couponId, 0);
        $resource->decrementCustomerTimesUsed($customerId, $couponId);
        $resource->decrementCustomerTimesUsed($customerId, $couponId);
        $resource->decrementCustomerTimesUsed($customerId, $couponId);

        $usage = new \Maho\DataObject();
        $resource->loadByCustomerCoupon($usage, $customerId, $couponId);
        expect((int) $usage->getTimesUsed())->toBe(0);
    });

    test('per-customer rule counter never exceeds uses per customer', function () {
        $customer = usageLimitCustomer('rule-per-customer@example.com');
        $rule = usageLimitRule(2);
        $customerId = (int) $customer->getId();
        $ruleId = (int) $rule->getId();
        $resource = Mage::getResourceModel('salesrule/rule_customer');

        $resource->incrementTimesUsed($customerId, $ruleId, 2);
        $resource->incrementTimesUsed($customerId, $ruleId, 2);
        $resource->incrementTimesUsed($customerId, $ruleId, 2);

        $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, $ruleId);
        expect((int) $ruleCustomer->getTimesUsed())->toBe(2);

        $resource->decrementTimesUsed($customerId, $ruleId);
        $resource->decrementTimesUsed($customerId, $ruleId);
        $resource->decrementTimesUsed($customerId, $ruleId);
        $ruleCustomer->loadByCustomerRule($customerId, $ruleId);
        expect((int) $ruleCustomer->getTimesUsed())->toBe(0);
    });

    test('an order placed past the coupon limit stands and leaves the coupon counter at its limit', function () {
        $customer = usageLimitCustomer('order-beyond-limit@example.com');
        $rule = usageLimitRule();
        $coupon = usageLimitCoupon($rule, 1);
        $customerId = (int) $customer->getId();

        $first = usageLimitOrder($rule, $coupon, $customerId);
        Mage::dispatchEvent('sales_order_place_after', ['order' => $first]);

        $second = usageLimitOrder($rule, $coupon, $customerId);
        Mage::dispatchEvent('sales_order_place_after', ['order' => $second]);

        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(1);
        // The rule counter is a tally, not a limit, so both orders count.
        expect((int) $rule->load($rule->getId())->getTimesUsed())->toBe(2);

        $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, (int) $rule->getId());
        expect((int) $ruleCustomer->getTimesUsed())->toBe(2);
    });

    test('payment cancel releases every counter', function () {
        $customer = usageLimitCustomer('order-cancel-release@example.com');
        $rule = usageLimitRule(1);
        $coupon = usageLimitCoupon($rule, 1, 1);
        $customerId = (int) $customer->getId();

        $order = usageLimitOrder($rule, $coupon, $customerId);
        Mage::dispatchEvent('sales_order_place_after', ['order' => $order]);

        $payment = Mage::getModel('sales/order_payment')->setOrder($order);
        Mage::dispatchEvent('sales_order_payment_cancel', ['payment' => $payment]);

        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(0);
        expect((int) $rule->load($rule->getId())->getTimesUsed())->toBe(0);

        $usage = new \Maho\DataObject();
        Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon($usage, $customerId, (int) $coupon->getId());
        expect((int) $usage->getTimesUsed())->toBe(0);

        $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, (int) $rule->getId());
        expect((int) $ruleCustomer->getTimesUsed())->toBe(0);

        Mage::dispatchEvent('sales_order_place_after', ['order' => usageLimitOrder($rule, $coupon, $customerId)]);
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(1);
    });
});
