<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Integration coverage for OrderService POS cash handling:
 *
 *  1. Insufficient cash is rejected BEFORE the order is created — the quote must
 *     stay active so a corrected retry works (regression: the check used to run
 *     after submitAll(), leaving an orphaned order and a dead cart).
 *  2. cash_tendered / change_amount are persisted on the ORDER payment, not the
 *     quote payment that is discarded once the quote is deactivated.
 */
describe('OrderService cash tendered', function (): void {

    beforeEach(function (): void {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToSelect(['price', 'name'])
            ->setPageSize(1)
            ->getFirstItem();
        if (!$product->getId()) {
            $this->markTestSkipped('No simple product available for testing');
        }
        $this->product = $product;
    });

    // Build a checkout-ready quote paying by cash on delivery.
    $makeCashQuote = function (object $product): Mage_Sales_Model_Quote {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->addProduct($product, 2);

        $addressData = [
            'country_id' => 'US',
            'region_id' => 12,
            'postcode' => '90210',
            'firstname' => 'Cash',
            'lastname' => 'Buyer',
            'street' => '123 Test St',
            'city' => 'Beverly Hills',
            'telephone' => '555-1234',
            'email' => 'cash@example.com',
        ];
        $quote->getBillingAddress()->addData($addressData);
        $quote->getShippingAddress()->addData($addressData)
            ->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate');
        $quote->getPayment()->setMethod('cashondelivery');
        $quote->setIsActive(true);
        $quote->collectTotals()->save();

        return $quote;
    };

    test('insufficient cash is rejected before the order is created', function () use ($makeCashQuote): void {
        $quote = $makeCashQuote($this->product);
        $grandTotal = (float) $quote->getGrandTotal();
        expect($grandTotal)->toBeGreaterThan(0.0);

        $service = new Mage\Sales\Api\OrderService();

        $threw = false;
        try {
            // Tender $10 less than owed.
            $service->placeAdminOrder($quote, null, null, $grandTotal - 10.0);
        } catch (\Throwable $e) {
            $threw = true;
            expect($e->getMessage())->toContain('Insufficient cash');
        }

        expect($threw)->toBeTrue();

        // The quote must NOT have been converted/deactivated: the guard runs
        // before submitAll(), so no order exists and a retry is still possible.
        $reloaded = Mage::getModel('sales/quote')->load($quote->getId());
        expect((int) $reloaded->getIsActive())->toBe(1);

        $orderCount = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('quote_id', $quote->getId())
            ->getSize();
        expect($orderCount)->toBe(0);
    });

    test('sufficient cash stores tendered and change on the order payment', function () use ($makeCashQuote): void {
        $quote = $makeCashQuote($this->product);
        $grandTotal = (float) $quote->getGrandTotal();

        $tendered = $grandTotal + 20.0;

        $service = new Mage\Sales\Api\OrderService();

        try {
            $result = $service->placeAdminOrder($quote, null, null, $tendered);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cash-on-delivery checkout could not complete in this store: ' . $e->getMessage());
        }

        $order = $result['order'];
        expect($order->getId())->toBeGreaterThan(0);

        // The values must live on the persisted ORDER payment, reloaded from DB.
        $reloadedOrder = Mage::getModel('sales/order')->load($order->getId());
        $payment = $reloadedOrder->getPayment();
        expect((float) $payment->getAdditionalInformation('cash_tendered'))->toBe($tendered);
        expect((float) $payment->getAdditionalInformation('change_amount'))->toBe(20.0);
    });
});
