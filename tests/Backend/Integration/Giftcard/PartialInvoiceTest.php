<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function placeOrderWithGiftcard(Maho_Giftcard_Model_Giftcard $giftcard, Mage_Catalog_Model_Product $product): Mage_Sales_Model_Order
{
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->addProduct($product, 4);

    $addressData = [
        'country_id' => 'US',
        'region_id' => 12,
        'postcode' => '90210',
        'firstname' => 'Test',
        'lastname' => 'Customer',
        'street' => '123 Test St',
        'city' => 'Beverly Hills',
        'telephone' => '555-1234',
        'email' => 'test@example.com',
    ];
    $quote->getBillingAddress()->addData($addressData);
    $quote->getShippingAddress()->addData($addressData)
        ->setCollectShippingRates()
        ->setShippingMethod('flatrate_flatrate');
    $quote->getPayment()->importData(['method' => 'checkmo']);

    Mage::getService('checkout/cart')->applyGiftcard($quote, $giftcard->getCode());

    return (new Mage_Sales_Model_Service_Quote($quote))->submitOrder();
}

function invoiceHalfOfOrder(Mage_Sales_Model_Order $order): Mage_Sales_Model_Order_Invoice
{
    $item = $order->getAllVisibleItems()[0];
    $invoice = $order->prepareInvoice([$item->getId() => 2]);
    $invoice->register();
    Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();

    return $invoice;
}

describe('Gift card amount on partial invoices', function (): void {
    beforeEach(function (): void {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('price', ['gt' => 10])
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }

        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setCode(Mage::helper('giftcard')->generateCode());
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setWebsiteIds([1]);
        $this->giftcard->setBalance(10.00);
        $this->giftcard->setInitialBalance(10.00);
        $this->giftcard->save();

        $this->order = placeOrderWithGiftcard($this->giftcard, $product);
        expect((float) $this->order->getBaseGiftcardAmount())->toBe(10.00);
        expect((float) $this->order->getBaseGrandTotal())->toBeGreaterThan(0.0);
    });

    test('the invoices of an order share its gift card amount', function (): void {
        $first = invoiceHalfOfOrder($this->order);
        $second = invoiceHalfOfOrder($this->order);

        expect((float) $first->getBaseGiftcardAmount())->toBeGreaterThan(0.0)->toBeLessThan(10.00);
        expect(round((float) $first->getBaseGiftcardAmount() + (float) $second->getBaseGiftcardAmount(), 4))->toBe(10.00);
        expect(round((float) $first->getGiftcardAmount() + (float) $second->getGiftcardAmount(), 4))
            ->toBe(round((float) $this->order->getGiftcardAmount(), 4));
        expect(round((float) $first->getBaseGrandTotal() + (float) $second->getBaseGrandTotal(), 4))
            ->toBe(round((float) $this->order->getBaseGrandTotal(), 4));
    });

    test('a cancel gives back only the gift card amount that no invoice used', function (): void {
        $invoice = invoiceHalfOfOrder($this->order);
        $invoicedAmount = (float) $invoice->getBaseGiftcardAmount();

        $this->order->cancel();
        $this->order->save();

        expect((float) $this->order->getBaseSubtotalCanceled())->toBeGreaterThan(0.0);
        $reloaded = Mage::getModel('giftcard/giftcard')->load($this->giftcard->getId());
        expect((float) $reloaded->getBalance())->toBe(round(10.00 - $invoicedAmount, 4));
    });
});
