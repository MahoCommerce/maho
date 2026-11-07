<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * When the cart of the checkout session is saved, the session keeps its quote id.
 * The observer used to call getQuoteId() instead of setQuoteId(), so it did nothing.
 */
function quoteSaveSessionObserve(Mage_Sales_Model_Quote $quote): void
{
    $event = new \Maho\Event(['quote' => $quote]);
    $observer = new \Maho\Event\Observer();
    $observer->setEvent($event);
    Mage::getModel('checkout/observer')->salesQuoteSaveAfter($observer);
}

it('keeps the id of a saved checkout cart in the session', function () {
    $session = Mage::getSingleton('checkout/session');
    $session->setQuoteId(null);
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsCheckoutCart();
    $quote->save();

    try {
        quoteSaveSessionObserve($quote);

        expect($session->getQuoteId())->toBe((int) $quote->getId());
    } finally {
        $session->setQuoteId(null);
        $quote->delete();
    }
});

it('ignores a quote that is not the checkout cart', function () {
    $session = Mage::getSingleton('checkout/session');
    $session->setQuoteId(null);
    $quote = Mage::getModel('sales/quote')->setStoreId(1);
    $quote->save();

    try {
        quoteSaveSessionObserve($quote);

        expect($session->getQuoteId())->toBeNull();
    } finally {
        $quote->delete();
    }
});
