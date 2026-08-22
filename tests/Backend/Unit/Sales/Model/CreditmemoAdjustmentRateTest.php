<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * An adjustment on an order with no stamped rate keeps its base amount and stores null on the
 * order-currency side. It cannot throw: registerRefundNotification() builds one for every
 * partial refund, and a rejected gateway notification retries forever.
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

    expect((float) $creditmemo->getData('base_adjustment_positive'))->toBe(50.0);
    expect($creditmemo->getData('adjustment_positive'))->toBeNull();
    expect((float) $creditmemo->getData('base_adjustment_negative'))->toBe(30.0);
    expect($creditmemo->getData('adjustment_negative'))->toBeNull();
});

it('takes a partial refund notification on an order that has no rate', function () {
    $creditmemo = creditmemoForOrder(null);

    expect(fn() => $creditmemo->setAdjustmentNegative(120.0))->not->toThrow(Mage_Core_Exception::class);
    expect((float) $creditmemo->getData('base_adjustment_negative'))->toBe(120.0);
});

// Every credit memo posts both adjustment fields, as zero on a plain refund, so zero must pass
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
