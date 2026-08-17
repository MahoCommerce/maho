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
 * cannot show it: multiplying by no rate gives zero, which refunds an admin's 50 as a visible 0
 * while base_adjustment_positive keeps the real amount.
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

it('refuses an adjustment on an order that has no rate to its own currency', function () {
    $creditmemo = creditmemoForOrder(null);

    expect(fn() => $creditmemo->setAdjustmentPositive(50.0))->toThrow(Mage_Core_Exception::class);
    expect(fn() => $creditmemo->setAdjustmentNegative(50.0))->toThrow(Mage_Core_Exception::class);

    // Neither side is written: the amount is converted before either field is set.
    expect($creditmemo->getData('adjustment_positive'))->toBeNull();
    expect($creditmemo->getData('base_adjustment_positive'))->toBeNull();
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
