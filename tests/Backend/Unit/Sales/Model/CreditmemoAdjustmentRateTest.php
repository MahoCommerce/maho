<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A credit memo adjustment is entered in the order's base currency and shown in the currency the
 * customer paid in, using the rate the order was stamped with. An order that has no such rate
 * cannot show it: multiplying by no rate gives zero, which reads as an adjustment of nothing
 * rather than one that could not be converted. The base amount beside it stays real either way.
 *
 * Refusing outright is not open here. Mage_Sales_Model_Order_Payment::registerRefundNotification()
 * builds a non-zero adjustment for every partial refund, so a throw would reject the gateway's
 * notification, which has nobody to show a message to and retries forever.
 */
function creditmemoForOrder(?float $storeToOrderRate, string $orderCurrency = 'XTN'): Mage_Sales_Model_Order_Creditmemo
{
    /** @var Mage_Sales_Model_Order $order */
    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setBaseCurrencyCode((string) Mage::app()->getStore(1)->getBaseCurrencyCode())
        ->setOrderCurrencyCode($orderCurrency)
        ->setData('store_to_order_rate', $storeToOrderRate);

    /** @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
    $creditmemo = Mage::getModel('sales/order_creditmemo');

    return $creditmemo->setOrder($order);
}

it('records no adjustment in an order currency it cannot convert into', function () {
    $creditmemo = creditmemoForOrder(null);

    $creditmemo->setAdjustmentPositive(50.0)->setAdjustmentNegative(30.0);

    // The base side is the real amount; the customer-facing side says it has no answer,
    // rather than saying the answer is nothing.
    expect((float) $creditmemo->getData('base_adjustment_positive'))->toBe(50.0);
    expect($creditmemo->getData('adjustment_positive'))->toBeNull();
    expect((float) $creditmemo->getData('base_adjustment_negative'))->toBe(30.0);
    expect($creditmemo->getData('adjustment_negative'))->toBeNull();
});

// The shape that made refusing impossible: a partial refund notification carries a non-zero
// adjustment, and it reaches the same setter with no admin session behind it.
it('takes a partial refund notification on an order that has no rate', function () {
    $creditmemo = creditmemoForOrder(null);

    expect(fn() => $creditmemo->setAdjustmentNegative(120.0))->not->toThrow(Mage_Core_Exception::class);
    expect((float) $creditmemo->getData('base_adjustment_negative'))->toBe(120.0);
});

/*
 * Both adjustment fields are posted by every credit memo, as zero when the refund carries no
 * adjustment (adjustments.phtml renders them unconditionally, and Order/Payment.php:836,847 sends
 * base_grand_total - amount, which is zero on a full refund). Refusing those would stop a plain
 * refund going through at all, and an IPN retrying it forever.
 */
it('takes a refund with no adjustment on an order that has no rate', function () {
    $creditmemo = creditmemoForOrder(null)->setAdjustmentPositive(0.0);

    expect((float) $creditmemo->getData('adjustment_positive'))->toBe(0.0);
    expect((float) $creditmemo->getData('base_adjustment_positive'))->toBe(0.0);
});

// Orders placed before a missing rate could be stamped as one carry 0, and are left as they are.
it('converts an adjustment on an order stamped with a zero rate as it always did', function () {
    $creditmemo = creditmemoForOrder(0.0)->setAdjustmentPositive(50.0);

    expect((float) $creditmemo->getData('base_adjustment_positive'))->toBe(50.0);
    expect((float) $creditmemo->getData('adjustment_positive'))->toBe(0.0);
});

it('converts an adjustment at the rate the order was placed with', function () {
    $creditmemo = creditmemoForOrder(2.0, 'EUR')->setAdjustmentPositive(50.0);

    expect((float) $creditmemo->getData('base_adjustment_positive'))->toBe(50.0);
    expect((float) $creditmemo->getData('adjustment_positive'))->toBe(100.0);
});
