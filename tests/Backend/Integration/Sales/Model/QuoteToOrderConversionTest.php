<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * copyFieldset() reads the source through its typed getters and writes the
 * target through its typed setters. Database-shaped strings and empty
 * strings in nullable columns must convert in both directions without a
 * TypeError.
 */

function conversionQuote(): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->setData('customer_id', '');
    $quote->setData('customer_group_id', '1');
    $quote->setData('customer_is_guest', '1');
    $quote->setData('customer_gender', '');
    $quote->setData('customer_email', 'test@example.com');
    $quote->setData('base_grand_total', '25.5000');
    $quote->setData('items_qty', '2.0000');
    $quote->setData('is_virtual', '0');
    $quote->setData('applied_rule_ids', '1,2');
    return $quote;
}

function conversionQuoteAddress(Mage_Sales_Model_Quote $quote, string $type): Mage_Sales_Model_Quote_Address
{
    $address = Mage::getModel('sales/quote_address');
    $address->setQuote($quote);
    $address->setAddressType($type);
    $address->setData('customer_address_id', '');
    $address->setData('region_id', '');
    $address->setData('firstname', 'Jane');
    $address->setData('lastname', 'Doe');
    $address->setData('postcode', '01234');
    $address->setData('country_id', 'US');
    $address->setData('street', "1 Main St\nSuite 2");
    $address->setData('subtotal', '20.0000');
    $address->setData('grand_total', '25.5000');
    $address->setData('base_grand_total', '25.5000');
    $address->setData('weight', '1.5000');
    $address->setData('vat_is_valid', '');
    return $address;
}

describe('quote to order', function (): void {
    test('converts the quote with typed values', function (): void {
        $order = Mage::getModel('sales/convert_quote')->toOrder(conversionQuote());

        expect($order->getCustomerId())->toBe(0)
            ->and($order->getCustomerGroupId())->toBe(1)
            ->and($order->getCustomerIsGuest())->toBe(true)
            ->and($order->getCustomerGender())->toBe(0)
            ->and($order->getQuoteBaseGrandTotal())->toBe(25.5)
            ->and($order->getTotalQtyOrdered())->toBe(2.0)
            ->and($order->getAppliedRuleIds())->toBe('1,2');
    });

    test('converts the quote address to the order and to an order address', function (): void {
        $quote = conversionQuote();
        $address = conversionQuoteAddress($quote, 'shipping');

        $order = Mage::getModel('sales/convert_quote')->addressToOrder($address);
        $orderAddress = Mage::getModel('sales/convert_quote')->addressToOrderAddress($address);

        expect($order->getGrandTotal())->toBe(25.5)
            ->and($order->getWeight())->toBe(1.5)
            ->and($orderAddress->getRegionId())->toBeNull()
            ->and($orderAddress->getPostcode())->toBe('01234')
            ->and($orderAddress->getStreet(-1))->toBe("1 Main St\nSuite 2")
            ->and($orderAddress->getVatIsValid())->toBe(false);
    });

    test('converts the quote payment and item', function (): void {
        $quote = conversionQuote();
        $payment = Mage::getModel('sales/quote_payment');
        $payment->setQuote($quote);
        $payment->setData('method', 'checkmo');
        $payment->setData('cc_exp_month', '7');
        $payment->setData('cc_exp_year', '2030');

        $orderPayment = Mage::getModel('sales/convert_quote')->paymentToOrderPayment($payment);

        $product = Mage::getModel('catalog/product');
        $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
        $product->setId(1);
        $item = Mage::getModel('sales/quote_item');
        $item->setQuote($quote);
        $item->setData('product', $product);
        $item->setData('product_id', '1');
        $item->setData('sku', 'sku-1');
        $item->setData('qty', '2.0000');
        $item->setData('row_total', '20.0000');
        $item->setData('is_qty_decimal', '0');
        $item->setData('discount_amount', '');
        $item->setData('applied_rule_ids', '');

        $orderItem = Mage::getModel('sales/convert_quote')->itemToOrderItem($item);

        expect($orderPayment->getMethod())->toBe('checkmo')
            ->and($orderPayment->getCcExpMonth())->toBe('7')
            ->and($orderItem->getQtyOrdered())->toBe(2.0)
            ->and($orderItem->getRowTotal())->toBe(20.0)
            ->and($orderItem->getIsQtyDecimal())->toBe(false)
            ->and($orderItem->getDiscountAmount())->toBe(0.0)
            ->and($orderItem->getProductId())->toBe(1);
    });
});

function conversionOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setStoreId(1);
    $order->setData('customer_id', '');
    $order->setData('customer_group_id', '1');
    $order->setData('customer_email', 'test@example.com');
    $order->setData('grand_total', '25.5000');
    $order->setData('base_grand_total', '25.5000');
    $order->setData('order_currency_code', 'USD');
    $order->setData('base_to_order_rate', '1.0000');
    $order->setData('shipping_tax_amount', '');
    return $order;
}

describe('order to quote and to documents', function (): void {
    test('converts the order back to a quote and to the documents', function (): void {
        $order = conversionOrder();
        $converter = Mage::getModel('sales/convert_order');

        $quote = $converter->toQuote($order);
        $invoice = $converter->toInvoice($order);
        $shipment = $converter->toShipment($order);
        $creditmemo = $converter->toCreditmemo($order);

        expect($quote->getCustomerId())->toBe(0)
            ->and($quote->getGrandTotal())->toBe(25.5)
            ->and($quote->getQuoteCurrencyCode())->toBe('USD')
            ->and($quote->getBaseToQuoteRate())->toBe(1.0)
            ->and($invoice->getOrderCurrencyCode())->toBe('USD')
            ->and($shipment->getOrderCurrencyCode())->toBe('USD')
            ->and($creditmemo->getShippingTaxAmount())->toBe(0.0);
    });

    test('converts the order address, payment and item', function (): void {
        $order = conversionOrder();
        $converter = Mage::getModel('sales/convert_order');

        $address = Mage::getModel('sales/order_address');
        $address->setOrder($order);
        $address->setData('region_id', '');
        $address->setData('postcode', '01234');
        $address->setData('street', "1 Main St\nSuite 2");
        $quoteAddress = $converter->addressToQuoteAddress($address);

        $payment = Mage::getModel('sales/order_payment');
        $payment->setOrder($order);
        $payment->setData('method', 'checkmo');
        $payment->setData('cc_exp_month', '07');
        $quotePayment = $converter->paymentToQuotePayment($payment);

        $item = Mage::getModel('sales/order_item');
        $item->setOrder($order);
        $item->setData('sku', 'sku-1');
        $item->setData('price', '10.0000');
        $item->setData('base_price', '10.0000');
        $item->setData('weight', '');
        $item->setData('discount_percent', '');
        $quoteItem = $converter->itemToQuoteItem($item);
        $invoiceItem = $converter->itemToInvoiceItem($item);
        $shipmentItem = $converter->itemToShipmentItem($item);
        $creditmemoItem = $converter->itemToCreditmemoItem($item);

        expect($quoteAddress->getRegionId())->toBeNull()
            ->and($quoteAddress->getStreet(-1))->toBe("1 Main St\nSuite 2")
            ->and($quotePayment->getCcExpMonth())->toBe('07')
            ->and($quoteItem->getCustomPrice())->toBe(10.0)
            ->and($quoteItem->getWeight())->toBe(0.0)
            ->and($invoiceItem->getBasePrice())->toBe(10.0)
            ->and($shipmentItem->getPrice())->toBe(10.0)
            ->and($creditmemoItem->getBasePrice())->toBe(10.0);
    });

    test('copies an order address into a quote address for the admin reorder', function (): void {
        $address = Mage::getModel('sales/order_address');
        $address->setData('region_id', '');
        $address->setData('customer_address_id', '');
        $address->setData('postcode', '01234');

        $quoteAddress = Mage::getModel('sales/quote_address');
        $quoteAddress->setCustomerAddressId(null);
        Mage::helper('core')->copyFieldset('sales_copy_order_billing_address', 'to_order', $address, $quoteAddress);

        expect($quoteAddress->getCustomerAddressId())->toBe(0)
            ->and($quoteAddress->getRegionId())->toBeNull()
            ->and($quoteAddress->getPostcode())->toBe('01234');
    });
});
