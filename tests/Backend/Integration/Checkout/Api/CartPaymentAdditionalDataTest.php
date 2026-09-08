<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1335: additionalData on the set-payment-method API must reach payment
 * methods as top-level keys, because assignData() reads flat keys (as the
 * onepage and SOAP flows deliver them), not an M2-style additional_data array.
 *
 * Config changes need no restore: MahoBackendTestCase::tearDown() runs Mage::reset().
 */
describe('set-payment-method additionalData', function (): void {

    it('delivers additionalData to the payment method as top-level keys', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/purchaseorder/active', '1');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            (new CartService())->setPaymentMethod($loaded, 'purchaseorder', ['po_number' => 'PO-1335']);

            expect($loaded->getPayment()->getMethod())->toBe('purchaseorder')
                ->and($loaded->getPayment()->getPoNumber())->toBe('PO-1335');
        } finally {
            $quote->delete();
        }
    });

    it('strips reserved keys and non-scalar values from additionalData', function (): void {
        $built = CartService::buildPaymentImportData('checkmo', [
            'method' => 'purchaseorder',
            'checks' => 0,
            'additional_data' => ['x' => 'y'],
            'additional_information' => ['x' => 'y'],
            'method_instance' => 'x',
            'quote_id' => 99,
            'cc_number' => '4111111111111111',
            'cc_type' => 'VI',
            // asserts "this cart is already paid" without the storefront's replay checks
            'paypal_order_id' => 'REPLAYED',
            'paypal_authorization_id' => 'REPLAYED',
            'paypal_capture_id' => 'REPLAYED',
            'po_comment' => ['x'],
            'po_number' => 'PO-1335',
        ], Mage_Payment_Model_Method_Abstract::CHECKS_CHECKOUT);

        expect($built)->toBe([
            'po_number' => 'PO-1335',
            'method' => 'checkmo',
            'checks' => Mage_Payment_Model_Method_Abstract::CHECKS_CHECKOUT,
        ]);
    });

    it('keeps a copy of the accepted additionalData on the payment', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/purchaseorder/active', '1');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            // txn_ref has no column of its own, so the flat delivery alone
            // would drop it at save time.
            (new CartService())->setPaymentMethod($loaded, 'purchaseorder', [
                'po_number' => 'PO-1335',
                'txn_ref' => 'TXN-42',
                'cc_number' => '4111111111111111',
            ]);

            $backup = $loaded->getPayment()->getAdditionalInformation(CartService::PAYMENT_ADDITIONAL_DATA_KEY);

            expect($backup)->toBe(['po_number' => 'PO-1335', 'txn_ref' => 'TXN-42']);
        } finally {
            $quote->delete();
        }
    });

    it('clears the copy when the client switches to a method without additionalData', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/purchaseorder/active', '1');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            $service = new CartService();
            $service->setPaymentMethod($loaded, 'purchaseorder', ['po_number' => 'PO-1335']);
            $service->setPaymentMethod($loaded, 'checkmo');

            expect($loaded->getPayment()->getAdditionalInformation(CartService::PAYMENT_ADDITIONAL_DATA_KEY))
                ->toBeNull();
        } finally {
            $quote->delete();
        }
    });

    it('rejects a method whose minimum order total exceeds the quote total', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/checkmo/min_order_total', '999999');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());

            // The message distinguishes the importData() checks from the
            // earlier assertPaymentMethodAvailable() gate, which would also
            // throw BadRequestHttpException if checkmo were simply inactive.
            expect(fn() => (new CartService())->setPaymentMethod($loaded, 'checkmo'))
                ->toThrow(BadRequestHttpException::class, 'Payment method is not available: ');
        } finally {
            $quote->delete();
        }
    });

    it('treats a card method as unusable over the API', function (): void {
        // buildPaymentImportData() strips every cc_* key, so Mage_Payment_Model_Method_Cc::validate()
        // can never pass. Such a method must not reach the setter nor the advertised list.
        expect(CartService::isMethodUsableOverApi(new Mage_Paygate_Model_Authorizenet()))->toBeFalse()
            ->and(CartService::isMethodUsableOverApi(new Mage_Payment_Model_Method_Checkmo()))->toBeTrue();
    });

    it('does not advertise a method the setter would reject', function (): void {
        $product = loadSimplePricedProduct();
        $quote = createPricedQuote($product);
        Mage::app()->getStore(1)->setConfig('payment/checkmo/min_order_total', '999999');

        try {
            $loaded = Mage::getModel('sales/quote')->setStoreId(1)->load($quote->getId());
            $codes = array_column((new CartMapper())->getAvailablePaymentMethods($loaded), 'code');

            expect($codes)->not->toContain('checkmo');
        } finally {
            $quote->delete();
        }
    });

});
