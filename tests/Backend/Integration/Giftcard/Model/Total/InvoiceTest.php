<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Invoice Total Collector Instantiation', function () {
    test('total collector can be instantiated', function () {
        $total = Mage::getModel('giftcard/total_invoice');
        expect($total)->toBeInstanceOf(Maho_Giftcard_Model_Total_Invoice::class);
        expect($total)->toBeInstanceOf(Mage_Sales_Model_Order_Invoice_Total_Abstract::class);
    });
});

describe('Invoice Total Collection', function () {
    beforeEach(function () {
        // Create a mock order with gift card amounts
        $this->order = Mage::getModel('sales/order');
        $this->order->setGiftcardAmount(50.00);
        $this->order->setBaseGiftcardAmount(50.00);

        // Create invoice
        $this->invoice = Mage::getModel('sales/order_invoice');
        $this->invoice->setOrder($this->order);
        $this->invoice->setGrandTotal(150.00);
        $this->invoice->setBaseGrandTotal(150.00);
    });

    test('applies gift card amount to invoice', function () {
        $total = Mage::getModel('giftcard/total_invoice');
        $total->collect($this->invoice);

        expect($this->invoice->getGiftcardAmount())->toBe(50.00);
        expect($this->invoice->getBaseGiftcardAmount())->toBe(50.00);
    });

    test('reduces grand total by gift card amount', function () {
        $total = Mage::getModel('giftcard/total_invoice');
        $total->collect($this->invoice);

        // 150 - 50 = 100
        expect($this->invoice->getGrandTotal())->toBe(100.00);
        expect($this->invoice->getBaseGrandTotal())->toBe(100.00);
    });

    test('handles order without gift card', function () {
        $orderNoGiftcard = Mage::getModel('sales/order');
        $orderNoGiftcard->setGiftcardAmount(0);
        $orderNoGiftcard->setBaseGiftcardAmount(0);

        $invoice = Mage::getModel('sales/order_invoice');
        $invoice->setOrder($orderNoGiftcard);
        $invoice->setGrandTotal(200.00);
        $invoice->setBaseGrandTotal(200.00);

        $total = Mage::getModel('giftcard/total_invoice');
        $total->collect($invoice);

        // Grand total unchanged
        expect($invoice->getGrandTotal())->toBe(200.00);
        expect($invoice->getBaseGrandTotal())->toBe(200.00);
    });

    test('handles null gift card amount', function () {
        $orderNull = Mage::getModel('sales/order');
        // No gift card amount set (null)

        $invoice = Mage::getModel('sales/order_invoice');
        $invoice->setOrder($orderNull);
        $invoice->setGrandTotal(100.00);
        $invoice->setBaseGrandTotal(100.00);

        $total = Mage::getModel('giftcard/total_invoice');
        $result = $total->collect($invoice);

        expect($result)->toBeInstanceOf(Maho_Giftcard_Model_Total_Invoice::class);
        expect($invoice->getGrandTotal())->toBe(100.00);
    });

    test('fully covers invoice when gift card equals grand total', function () {
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(100.00);
        $order->setBaseGiftcardAmount(100.00);

        $invoice = Mage::getModel('sales/order_invoice');
        $invoice->setOrder($order);
        $invoice->setGrandTotal(100.00);
        $invoice->setBaseGrandTotal(100.00);

        $total = Mage::getModel('giftcard/total_invoice');
        $total->collect($invoice);

        expect($invoice->getGrandTotal())->toBe(0.0);
        expect($invoice->getBaseGrandTotal())->toBe(0.0);
    });
});

describe('Invoice Total with Currency Conversion', function () {
    test('handles different base and display currencies', function () {
        $order = Mage::getModel('sales/order');
        $order->setGiftcardAmount(55.00); // Display currency
        $order->setBaseGiftcardAmount(50.00); // Base currency

        $invoice = Mage::getModel('sales/order_invoice');
        $invoice->setOrder($order);
        $invoice->setGrandTotal(165.00);
        $invoice->setBaseGrandTotal(150.00);

        $total = Mage::getModel('giftcard/total_invoice');
        $total->collect($invoice);

        expect($invoice->getGiftcardAmount())->toBe(55.00);
        expect($invoice->getBaseGiftcardAmount())->toBe(50.00);
        expect($invoice->getGrandTotal())->toBe(110.00); // 165 - 55
        expect($invoice->getBaseGrandTotal())->toBe(100.00); // 150 - 50
    });
});
