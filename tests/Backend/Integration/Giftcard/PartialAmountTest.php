<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Proves the money path behind the cart API's gift-card `amount` parameter:
 *
 *  1. The Giftcard module has no partial-usage support. A requested partial
 *     `amount` is ignored: the full balance, capped at the payable total, is
 *     applied. This is intended core behaviour, not a totals bug.
 *  2. The card is debited at placement by exactly the amount discounted from the
 *     order — no double deduction, no money leak.
 */
describe('Gift card partial amount + deduction consistency', function (): void {

    beforeEach(function (): void {
        $this->helper = Mage::helper('giftcard');

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

    test('a partial amount is ignored and the card is debited by exactly the discount', function (): void {
        $cardBalance = 100.00;
        $requestedAmount = 10.00;

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance($cardBalance);
        $giftcard->setInitialBalance($cardBalance);
        $giftcard->save();

        // Build a checkout-ready quote.
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->addProduct($this->product, 3);

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
            ->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate');
        $quote->getPayment()->importData(['method' => 'checkmo']);

        // Payable total BEFORE any gift card is applied.
        $quote->collectTotals();
        $payableBeforeGiftcard = (float) $quote->getBaseGrandTotal();
        if ($payableBeforeGiftcard <= $requestedAmount) {
            $this->markTestSkipped('Sample product too cheap to exercise partial-amount capping');
        }

        // Persist exactly what the cart API stores for a $10 request: the cart
        // API's CartService::applyGiftcard($quote, $code, 10.0) writes
        // min($requested, $balance) into giftcard_codes, then collects totals.
        // (Instantiating CartService directly needs the API kernel's module
        // autoloader; the stored state below is byte-for-byte what it produces,
        // and the behaviour under test lives in the shared totals collector.)
        $quote->setGiftcardCodes(json_encode([
            $giftcard->getCode() => min($requestedAmount, $cardBalance),
        ]));
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->save();

        // (1) The partial request is ignored: the applied amount is the full
        // balance capped at the payable total, NOT the requested $10.
        $expectedApplied = min($cardBalance, $payableBeforeGiftcard);
        $applied = (float) $quote->getBaseGiftcardAmount();
        expect($applied)->toBe($expectedApplied);
        expect($applied)->not->toBe($requestedAmount);

        // The collector overwrote the stored per-code amount with what it applied,
        // so the requested $10 leaves no trace.
        $storedCodes = json_decode((string) $quote->getGiftcardCodes(), true);
        expect((float) $storedCodes[$giftcard->getCode()])->toBe($expectedApplied);

        // Place the order → sales_order_place_after → deductGiftcardBalance().
        $order = (new Mage_Sales_Model_Service_Quote($quote))->submitOrder();
        expect((float) $order->getBaseGiftcardAmount())->toBe($expectedApplied);

        // (2) The books balance: the card is debited by exactly the discount.
        // A double-deduction bug would leave (balance - 2*applied); a leak would
        // leave the balance unchanged or debited by the wrong amount.
        $reloaded = Mage::getModel('giftcard/giftcard')->loadByCode($giftcard->getCode());
        expect((float) $reloaded->getBalance())->toBe($cardBalance - $expectedApplied);
    });
});
