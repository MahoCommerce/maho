<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
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
        ->setIsActive()
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

function usageLimitOrder(Mage_SalesRule_Model_Rule $rule, Mage_SalesRule_Model_Coupon $coupon, ?int $customerId, bool $save = true): Mage_Sales_Model_Order
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
    if ($save) {
        $order->save();
    }
    return $order;
}

/**
 * Save the order and place it in one transaction, as Mage_Sales_Model_Service_Quote::submitOrder() does.
 */
function usageLimitPlace(Mage_Sales_Model_Order $order): void
{
    Mage::getModel('core/resource_transaction')
        ->addObject($order)
        ->addCommitCallback(static fn() => Mage::dispatchEvent('sales_order_place_before', ['order' => $order]))
        ->save();
}

describe('coupon usage counters', function () {
    test('coupon increment stops at the usage limit', function () {
        $coupon = usageLimitCoupon(usageLimitRule(), 2);
        $resource = Mage::getResourceModel('salesrule/coupon');

        expect($resource->incrementTimesUsed((int) $coupon->getId()))->toBeTrue();
        expect($resource->incrementTimesUsed((int) $coupon->getId()))->toBeTrue();
        expect($resource->incrementTimesUsed((int) $coupon->getId()))->toBeFalse();

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

        expect($resource->incrementCustomerTimesUsed($customerId, $couponId, 1))->toBeTrue();
        expect($resource->incrementCustomerTimesUsed($customerId, $couponId, 1))->toBeFalse();

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

        expect($resource->incrementTimesUsed($customerId, $ruleId, 2))->toBeTrue();
        expect($resource->incrementTimesUsed($customerId, $ruleId, 2))->toBeTrue();
        expect($resource->incrementTimesUsed($customerId, $ruleId, 2))->toBeFalse();

        $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, $ruleId);
        expect((int) $ruleCustomer->getTimesUsed())->toBe(2);

        $resource->decrementTimesUsed($customerId, $ruleId);
        $resource->decrementTimesUsed($customerId, $ruleId);
        $resource->decrementTimesUsed($customerId, $ruleId);
        $ruleCustomer->loadByCustomerRule($customerId, $ruleId);
        expect((int) $ruleCustomer->getTimesUsed())->toBe(0);
    });

    test('an order past the coupon usage limit is refused and rolled back', function () {
        $customer = usageLimitCustomer('order-beyond-limit@example.com');
        $rule = usageLimitRule();
        $coupon = usageLimitCoupon($rule, 1);
        $customerId = (int) $customer->getId();

        usageLimitPlace(usageLimitOrder($rule, $coupon, $customerId, false));

        $second = usageLimitOrder($rule, $coupon, $customerId, false);
        expect(fn() => usageLimitPlace($second))
            ->toThrow(Mage_Core_Exception::class, 'was already used the maximum number of times');

        // The order got an ID when it was saved, and the rollback removed its row.
        expect($second->getId())->not->toBeNull();
        expect(Mage::getModel('sales/order')->load($second->getId())->getId())->toBeNull();
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(1);
        expect((int) $rule->load($rule->getId())->getTimesUsed())->toBe(1);

        $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, (int) $rule->getId());
        expect((int) $ruleCustomer->getTimesUsed())->toBe(1);
    });

    test('an order past the per-customer coupon limit is refused', function () {
        $customer = usageLimitCustomer('order-beyond-coupon-per-customer@example.com');
        $rule = usageLimitRule();
        $coupon = usageLimitCoupon($rule, null, 1);
        $customerId = (int) $customer->getId();

        usageLimitPlace(usageLimitOrder($rule, $coupon, $customerId, false));

        expect(fn() => usageLimitPlace(usageLimitOrder($rule, $coupon, $customerId, false)))
            ->toThrow(Mage_Core_Exception::class, 'You already used coupon code');
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(1);

        // Another customer still gets the coupon.
        $other = usageLimitCustomer('order-coupon-per-customer-other@example.com');
        usageLimitPlace(usageLimitOrder($rule, $coupon, (int) $other->getId(), false));
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(2);
    });

    test('an order past the uses per customer of a rule is refused', function () {
        $customer = usageLimitCustomer('order-beyond-rule-per-customer@example.com');
        $rule = usageLimitRule(1);
        $coupon = usageLimitCoupon($rule, null);
        $customerId = (int) $customer->getId();

        usageLimitPlace(usageLimitOrder($rule, $coupon, $customerId, false));

        expect(fn() => usageLimitPlace(usageLimitOrder($rule, $coupon, $customerId, false)))
            ->toThrow(Mage_Core_Exception::class, 'You already used a discount of this order');
        expect((int) $rule->load($rule->getId())->getTimesUsed())->toBe(1);
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(1);
    });

    test('guest orders are refused only by the coupon usage limit', function () {
        $rule = usageLimitRule(1);
        $coupon = usageLimitCoupon($rule, 2, 1);

        usageLimitPlace(usageLimitOrder($rule, $coupon, null, false));
        usageLimitPlace(usageLimitOrder($rule, $coupon, null, false));

        expect(fn() => usageLimitPlace(usageLimitOrder($rule, $coupon, null, false)))
            ->toThrow(Mage_Core_Exception::class, 'was already used the maximum number of times');
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(2);
    });

    test('payment cancel releases every counter', function () {
        $customer = usageLimitCustomer('order-cancel-release@example.com');
        $rule = usageLimitRule(1);
        $coupon = usageLimitCoupon($rule, 1, 1);
        $customerId = (int) $customer->getId();

        $order = usageLimitOrder($rule, $coupon, $customerId);
        Mage::dispatchEvent('sales_order_place_before', ['order' => $order]);

        $payment = Mage::getModel('sales/order_payment')->setOrder($order);
        Mage::dispatchEvent('sales_order_payment_cancel', ['payment' => $payment]);

        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(0);
        expect((int) $rule->load($rule->getId())->getTimesUsed())->toBe(0);

        $usage = new \Maho\DataObject();
        Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon($usage, $customerId, (int) $coupon->getId());
        expect((int) $usage->getTimesUsed())->toBe(0);

        $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, (int) $rule->getId());
        expect((int) $ruleCustomer->getTimesUsed())->toBe(0);

        Mage::dispatchEvent('sales_order_place_before', ['order' => usageLimitOrder($rule, $coupon, $customerId)]);
        expect((int) $coupon->load($coupon->getId())->getTimesUsed())->toBe(1);
    });
});
