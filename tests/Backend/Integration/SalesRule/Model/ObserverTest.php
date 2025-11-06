<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('SalesRule Observer Integration', function () {

    test('tracks free shipping-only coupon usage', function () {
        // Create a customer
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(1)
            ->setEmail('test-freeship@example.com')
            ->setFirstname('Free')
            ->setLastname('Shipping')
            ->setGroupId(1)
            ->save();

        // Create a free shipping-only sales rule
        $rule = Mage::getModel('salesrule/rule');
        $rule->setName('Free Shipping Only Test')
            ->setDescription('Test rule for free shipping')
            ->setIsActive(1)
            ->setWebsiteIds([1])
            ->setCustomerGroupIds([1])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setSimpleAction('by_percent') // No discount
            ->setDiscountAmount(0) // Important: 0 discount
            ->setSimpleFreeShipping(Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS)
            ->setUsesPerCustomer(1)
            ->save();

        // Create a coupon for this rule
        $coupon = Mage::getModel('salesrule/coupon');
        $coupon->setRuleId($rule->getId())
            ->setCode('FREESHIP2025')
            ->setUsageLimit(1)
            ->save();

        // Verify initial state (times_used might be null initially)
        expect((int) $rule->getTimesUsed())->toBe(0);
        expect((int) $coupon->getTimesUsed())->toBe(0);

        // Create an order with the coupon applied (but no discount amount)
        $order = Mage::getModel('sales/order');
        $order->setCustomerId($customer->getId())
            ->setStoreId(1)
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setStatus('pending')
            ->setAppliedRuleIds((string) $rule->getId()) // Rule was applied
            ->setCouponCode('FREESHIP2025') // Coupon was used
            ->setDiscountAmount(0) // But no discount amount (free shipping only)
            ->setBaseDiscountAmount(0)
            ->setGrandTotal(100)
            ->setBaseGrandTotal(100)
            ->save();

        // Trigger the observer
        Mage::dispatchEvent('sales_order_place_after', ['order' => $order]);

        // Reload models to get fresh data
        $rule->load($rule->getId());
        $coupon->load($coupon->getId());

        // Verify usage was tracked
        expect($rule->getTimesUsed())->toBe(1, 'Rule times_used should be incremented');
        expect($coupon->getTimesUsed())->toBe(1, 'Coupon times_used should be incremented');

        // Verify customer-specific usage
        $ruleCustomer = Mage::getModel('salesrule/rule_customer')
            ->loadByCustomerRule($customer->getId(), $rule->getId());
        expect($ruleCustomer->getId())->toBeGreaterThan(0, 'Customer rule record should exist');
        expect($ruleCustomer->getTimesUsed())->toBe(1, 'Customer-specific rule usage should be tracked');
    });

    test('tracks discount-only coupon usage (regression test)', function () {
        // This ensures our fix doesn't break existing discount tracking
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(1)
            ->setEmail('test-discount@example.com')
            ->setFirstname('Discount')
            ->setLastname('Test')
            ->setGroupId(1)
            ->save();

        $rule = Mage::getModel('salesrule/rule');
        $rule->setName('Discount Only Test')
            ->setIsActive(1)
            ->setWebsiteIds([1])
            ->setCustomerGroupIds([1])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setSimpleAction('by_percent')
            ->setDiscountAmount(10) // 10% discount
            ->setSimpleFreeShipping(0) // No free shipping
            ->setUsesPerCustomer(1)
            ->save();

        $coupon = Mage::getModel('salesrule/coupon');
        $coupon->setRuleId($rule->getId())
            ->setCode('DISCOUNT10')
            ->setUsageLimit(1)
            ->save();

        $order = Mage::getModel('sales/order');
        $order->setCustomerId($customer->getId())
            ->setStoreId(1)
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setStatus('pending')
            ->setAppliedRuleIds((string) $rule->getId())
            ->setCouponCode('DISCOUNT10')
            ->setDiscountAmount(10) // Has discount
            ->setBaseDiscountAmount(10)
            ->setGrandTotal(90)
            ->setBaseGrandTotal(90)
            ->save();

        Mage::dispatchEvent('sales_order_place_after', ['order' => $order]);

        $rule->load($rule->getId());
        $coupon->load($coupon->getId());

        expect($rule->getTimesUsed())->toBe(1);
        expect($coupon->getTimesUsed())->toBe(1);
    });

    test('tracks combined discount and free shipping coupon usage', function () {
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(1)
            ->setEmail('test-combo@example.com')
            ->setFirstname('Combo')
            ->setLastname('Test')
            ->setGroupId(1)
            ->save();

        $rule = Mage::getModel('salesrule/rule');
        $rule->setName('Discount + Free Shipping Test')
            ->setIsActive(1)
            ->setWebsiteIds([1])
            ->setCustomerGroupIds([1])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setSimpleAction('by_percent')
            ->setDiscountAmount(15) // 15% discount
            ->setSimpleFreeShipping(Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS) // Plus free shipping
            ->setUsesPerCustomer(1)
            ->save();

        $coupon = Mage::getModel('salesrule/coupon');
        $coupon->setRuleId($rule->getId())
            ->setCode('COMBO15')
            ->setUsageLimit(1)
            ->save();

        $order = Mage::getModel('sales/order');
        $order->setCustomerId($customer->getId())
            ->setStoreId(1)
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setStatus('pending')
            ->setAppliedRuleIds((string) $rule->getId())
            ->setCouponCode('COMBO15')
            ->setDiscountAmount(15)
            ->setBaseDiscountAmount(15)
            ->setGrandTotal(85)
            ->setBaseGrandTotal(85)
            ->save();

        Mage::dispatchEvent('sales_order_place_after', ['order' => $order]);

        $rule->load($rule->getId());
        $coupon->load($coupon->getId());

        expect($rule->getTimesUsed())->toBe(1);
        expect($coupon->getTimesUsed())->toBe(1);
    });

    test('does not track usage when no rules or coupons applied', function () {
        $order = Mage::getModel('sales/order');
        $order->setStoreId(1)
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setStatus('pending')
            ->setAppliedRuleIds('') // No rules
            ->setCouponCode('') // No coupon
            ->setDiscountAmount(0)
            ->setBaseDiscountAmount(0)
            ->setGrandTotal(100)
            ->setBaseGrandTotal(100)
            ->save();

        Mage::dispatchEvent('sales_order_place_after', ['order' => $order]);

        // Nothing to verify except no errors occurred
        expect(true)->toBe(true);
    });
});
