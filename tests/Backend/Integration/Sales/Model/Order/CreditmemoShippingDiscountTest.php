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

// Refunds a shipping-only credit memo without a payment, as an offline refund records it on the order.
function creditmemoShippingDiscountRefund(Mage_Sales_Model_Order $order, ?float $shippingAmount = null): Mage_Sales_Model_Order_Creditmemo
{
    $data = ['qtys' => creditmemoShippingDiscountQty($order, 0)];
    if ($shippingAmount !== null) {
        $data['shipping_amount'] = $shippingAmount;
    }
    $creditmemo = Mage::getService('sales/order_document')->buildCreditmemo($order, $data);
    $creditmemo->setPaymentRefundDisallowed(true)->refund();

    return $creditmemo;
}

describe('Credit memo shipping discount', function () {
    test('collects the discount after the shipping refund', function () {
        $codes = array_keys(Mage::getSingleton('sales/order_creditmemo_config')->getTotalModels());

        expect(array_search('discount', $codes, true))->toBeGreaterThan(array_search('shipping', $codes, true))
            ->and(array_search('discount', $codes, true))->toBeLessThan(array_search('tax', $codes, true));
    });

    test('deducts the shipping discount when no shipping amount is given', function () {
        $order = creditmemoShippingDiscountOrder();
        $creditmemo = Mage::getService('sales/order_document')
            ->buildCreditmemo($order, ['qtys' => creditmemoShippingDiscountQty($order, 1)]);

        // One item (50 - 5 discount) and the whole shipping (10 - 1 discount).
        expect((float) $creditmemo->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-6.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(54.0);
    });

    test('gives the same totals as an explicit shipping amount', function () {
        $order = creditmemoShippingDiscountOrder();
        $creditmemo = Mage::getService('sales/order_document')
            ->buildCreditmemo($order, ['qtys' => creditmemoShippingDiscountQty($order, 1), 'shipping_amount' => 10]);

        expect((float) $creditmemo->getBaseDiscountAmount())->toBe(-6.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(54.0);
    });

    test('deducts the share of the shipping discount for a partial shipping refund', function () {
        $order = creditmemoShippingDiscountOrder();
        $creditmemo = Mage::getService('sales/order_document')
            ->buildCreditmemo($order, ['qtys' => creditmemoShippingDiscountQty($order, 1), 'shipping_amount' => 5]);

        expect((float) $creditmemo->getBaseDiscountAmount())->toBe(-5.5)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(49.5);
    });

    test('rounds the shipping discount of a partial shipping refund', function () {
        $order = creditmemoShippingDiscountOrder();
        $order->getAllItems()[0]->setDiscountInvoiced(10.01)->setBaseDiscountInvoiced(10.01);
        $creditmemo = Mage::getService('sales/order_document')
            ->buildCreditmemo($order, ['qtys' => creditmemoShippingDiscountQty($order, 1), 'shipping_amount' => 3.33]);

        // The item discount is 5.005 and the shipping discount is 0.333: 5.338 rounds to 5.34.
        expect(round((float) $creditmemo->getBaseDiscountAmount(), 4))->toBe(-5.34)
            ->and(round((float) $creditmemo->getBaseGrandTotal(), 4))->toBe(47.99);
    });

    test('refunds the whole order for what the customer paid', function () {
        $creditmemo = Mage::getService('sales/order_document')
            ->buildCreditmemo(creditmemoShippingDiscountOrder());

        expect((float) $creditmemo->getBaseDiscountAmount())->toBe(-11.0)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(99.0);
    });

    test('splits the shipping discount over credit memos so that they add up to it', function () {
        $order = creditmemoShippingDiscountOrder();
        $order->setShippingDiscountAmount(0.1)->setBaseShippingDiscountAmount(0.1);
        $order->setDiscountAmount(-10.1)->setBaseDiscountAmount(-10.1);
        $order->setGrandTotal(99.9)->setBaseGrandTotal(99.9);
        $order->setTotalPaid(99.9)->setBaseTotalPaid(99.9);
        $order->save();

        // Each third of the shipping discount is 0.0333; rounding each on its own would deduct 0.09 in all.
        $discounts = [];
        $refunded = 0.0;
        foreach ([3.33, 3.33, null] as $shippingAmount) {
            $creditmemo = creditmemoShippingDiscountRefund($order, $shippingAmount);
            $discounts[] = (float) $creditmemo->getBaseDiscountAmount();
            $refunded += (float) $creditmemo->getBaseGrandTotal();
        }

        expect($discounts)->toBe([-0.03, -0.04, -0.03])
            ->and(round($refunded, 4))->toBe(9.9);
    });

    test('rounds the item discount the same whether the shipping is refunded or not', function () {
        // A discount of 0.008 over two items (0.004 each) and a shipping discount of 0.008 on 10.
        $order = creditmemoShippingDiscountOrder();
        $order->setShippingDiscountAmount(0.008)->setBaseShippingDiscountAmount(0.008);
        $order->setDiscountAmount(-0.016)->setBaseDiscountAmount(-0.016);
        $order->setGrandTotal(109.98)->setBaseGrandTotal(109.98);
        $order->setTotalPaid(109.98)->setBaseTotalPaid(109.98);
        $item = $order->getAllItems()[0];
        $item->setDiscountAmount(0.008)->setBaseDiscountAmount(0.008);
        $item->setDiscountInvoiced(0.008)->setBaseDiscountInvoiced(0.008);
        $order->save();

        $itemDiscount = function (float $shippingAmount) use ($order): float {
            $creditmemo = Mage::getService('sales/order_document')->buildCreditmemo($order, [
                'qtys' => creditmemoShippingDiscountQty($order, 1),
                'shipping_amount' => $shippingAmount,
            ]);
            foreach ($creditmemo->getAllItems() as $item) {
                if ($item->getQty() > 0) {
                    return (float) $item->getBaseDiscountAmount();
                }
            }
            return 0.0;
        };

        expect($itemDiscount(5))->toBe(0.0)
            ->and($itemDiscount(0))->toBe(0.0);
    });
});
