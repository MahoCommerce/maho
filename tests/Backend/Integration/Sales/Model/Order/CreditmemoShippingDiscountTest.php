<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// Two items of 50 and shipping of 10, with a 10% cart rule that also applies to shipping:
// the item discount is 10 and the shipping discount is 1, so the customer paid 99.
function creditmemoShippingDiscountOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('CM-SHIP-DISCOUNT-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setSubtotal(100.0)->setBaseSubtotal(100.0);
    $order->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $order->setShippingInclTax(10.0)->setBaseShippingInclTax(10.0);
    $order->setShippingDiscountAmount(1.0)->setBaseShippingDiscountAmount(1.0);
    $order->setDiscountAmount(-11.0)->setBaseDiscountAmount(-11.0);
    $order->setGrandTotal(99.0)->setBaseGrandTotal(99.0);
    $order->setTotalPaid(99.0)->setBaseTotalPaid(99.0);

    $item = Mage::getModel('sales/order_item');
    $item->setSku('cm-ship-discount');
    $item->setProductType('simple');
    $item->setQtyOrdered(2)->setQtyInvoiced(2);
    $item->setPrice(50.0)->setBasePrice(50.0);
    $item->setRowTotal(100.0)->setBaseRowTotal(100.0);
    $item->setRowInvoiced(100.0)->setBaseRowInvoiced(100.0);
    $item->setDiscountAmount(10.0)->setBaseDiscountAmount(10.0);
    $item->setDiscountInvoiced(10.0)->setBaseDiscountInvoiced(10.0);
    $order->addItem($item);
    $order->save();

    return $order;
}

function creditmemoShippingDiscountQty(Mage_Sales_Model_Order $order, float $qty): array
{
    return [(int) $order->getAllItems()[0]->getId() => $qty];
}

describe('Credit memo shipping discount', function () {
    test('collects the discount after the shipping refund', function () {
        $codes = array_keys(Mage::getSingleton('sales/order_creditmemo_config')->getTotalModels());

        expect(array_search('discount', $codes, true))->toBeGreaterThan(array_search('shipping', $codes, true));
    });

    test('deducts the shipping discount when no shipping amount is given', function () {
        $order = creditmemoShippingDiscountOrder();
        $creditmemo = Mage::getModel('sales/service_order', $order)
            ->prepareCreditmemo(['qtys' => creditmemoShippingDiscountQty($order, 1)]);

        // One item (50 - 5 discount) and the whole shipping (10 - 1 discount).
        expect((float) $creditmemo->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-6.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(54.0);
    });

    test('gives the same totals as an explicit shipping amount', function () {
        $order = creditmemoShippingDiscountOrder();
        $creditmemo = Mage::getModel('sales/service_order', $order)
            ->prepareCreditmemo(['qtys' => creditmemoShippingDiscountQty($order, 1), 'shipping_amount' => 10]);

        expect((float) $creditmemo->getBaseDiscountAmount())->toBe(-6.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(54.0);
    });

    test('deducts the share of the shipping discount for a partial shipping refund', function () {
        $order = creditmemoShippingDiscountOrder();
        $creditmemo = Mage::getModel('sales/service_order', $order)
            ->prepareCreditmemo(['qtys' => creditmemoShippingDiscountQty($order, 1), 'shipping_amount' => 5]);

        expect((float) $creditmemo->getBaseDiscountAmount())->toBe(-5.5)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(49.5);
    });

    test('refunds the whole order for what the customer paid', function () {
        $creditmemo = Mage::getModel('sales/service_order', creditmemoShippingDiscountOrder())
            ->prepareCreditmemo();

        expect((float) $creditmemo->getBaseDiscountAmount())->toBe(-11.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(99.0);
    });
});
