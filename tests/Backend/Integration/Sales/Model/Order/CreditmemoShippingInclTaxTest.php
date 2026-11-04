<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// Shipping of 10 plus 22% tax (12.20 incl. tax), with a 10% discount incl. tax on it: the shipping
// discount is 1.22, of which 0.22 is hidden tax, so the customer paid 12.20 - 1.22 = 10.98.
function creditmemoShippingInclTaxOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId('CM-SHIP-INCL-TAX-' . uniqid('', true));
    $order->setStoreId(1);
    $order->setBaseCurrencyCode('USD');
    $order->setOrderCurrencyCode('USD');
    $order->setBaseToOrderRate(1.0);
    $order->setShippingAmount(10.0)->setBaseShippingAmount(10.0);
    $order->setShippingInclTax(12.2)->setBaseShippingInclTax(12.2);
    $order->setShippingTaxAmount(1.98)->setBaseShippingTaxAmount(1.98);
    $order->setShippingHiddenTaxAmount(0.22)->setBaseShippingHiddenTaxAmount(0.22);
    $order->setShippingDiscountAmount(1.22)->setBaseShippingDiscountAmount(1.22);
    $order->setDiscountAmount(-1.22)->setBaseDiscountAmount(-1.22);
    $order->setTaxAmount(1.98)->setBaseTaxAmount(1.98);
    $order->setTaxInvoiced(1.98)->setBaseTaxInvoiced(1.98);
    $order->setHiddenTaxInvoiced(0.22)->setBaseHiddenTaxInvoiced(0.22);
    $order->setGrandTotal(10.98)->setBaseGrandTotal(10.98);
    $order->setTotalPaid(10.98)->setBaseTotalPaid(10.98);
    $order->save();

    return $order;
}

// The API and the admin form refund no item with this qty map.
function creditmemoShippingInclTaxMemo(Mage_Sales_Model_Order $order, array $data = []): Mage_Sales_Model_Order_Creditmemo
{
    return Mage::getModel('sales/service_order', $order)->prepareCreditmemo(['qtys' => [0 => 0]] + $data);
}

describe('Credit memo shipping displayed incl. tax', function () {
    beforeEach(function () {
        Mage::app()->getStore(1)->setConfig('tax/sales_display/shipping', Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);
    });

    afterEach(function () {
        Mage::app()->getStore(1)->setConfig('tax/sales_display/shipping', null);
    });

    test('offers the shipping of the order incl. tax', function () {
        $creditmemo = creditmemoShippingInclTaxMemo(creditmemoShippingInclTaxOrder());

        expect((float) $creditmemo->getBaseShippingInclTax())->toBe(12.2)
            ->and((float) $creditmemo->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-1.22)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(10.98);
    });

    test('refunds the whole shipping for the offered amount', function () {
        $order = creditmemoShippingInclTaxOrder();
        $offered = creditmemoShippingInclTaxMemo($order)->getBaseShippingInclTax();

        $creditmemo = creditmemoShippingInclTaxMemo($order, ['shipping_amount' => $offered]);

        expect((float) $creditmemo->getBaseShippingAmount())->toBe(10.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-1.22)
            ->and((float) $creditmemo->getBaseGrandTotal())->toBe(10.98);
    });

    test('deducts the share of the shipping discount for half of the shipping', function () {
        $creditmemo = creditmemoShippingInclTaxMemo(creditmemoShippingInclTaxOrder(), ['shipping_amount' => 6.1]);

        expect((float) $creditmemo->getBaseShippingAmount())->toBe(5.0)
            ->and((float) $creditmemo->getBaseDiscountAmount())->toBe(-0.61);
    });

    test('offers the shipping plus its tax for an order without the shipping incl. tax', function () {
        $order = creditmemoShippingInclTaxOrder();
        $order->setShippingInclTax(null)->setBaseShippingInclTax(null);

        $creditmemo = creditmemoShippingInclTaxMemo($order);

        expect((float) $creditmemo->getBaseShippingInclTax())->toBe(11.98)
            ->and((float) $creditmemo->getBaseShippingAmount())->toBe(10.0);
    });
});
