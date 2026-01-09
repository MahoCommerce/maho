<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Gift Card Payment Method Availability', function () {
    beforeEach(function () {
        $this->payment = Mage::getModel('giftcard/payment');
    });

    test('is available when gift card fully covers order', function () {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->setGiftcardAmount(-100.00);
        $quote->setGrandTotal(0.00);

        expect($this->payment->isAvailable($quote))->toBeTrue();
    });

    test('is available when gift card covers order with small rounding difference', function () {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->setGiftcardAmount(-100.00);
        $quote->setGrandTotal(0.01); // Small rounding difference

        expect($this->payment->isAvailable($quote))->toBeTrue();
    });

    test('is not available when gift card only partially covers order', function () {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->setGiftcardAmount(-50.00);
        $quote->setGrandTotal(50.00); // Still $50 to pay

        expect($this->payment->isAvailable($quote))->toBeFalse();
    });

    test('is not available when no gift card applied', function () {
        $quote = Mage::getModel('sales/quote');
        $quote->setStoreId(1);
        $quote->setGiftcardAmount(0);
        $quote->setGrandTotal(100.00);

        expect($this->payment->isAvailable($quote))->toBeFalse();
    });

    test('is not available without quote', function () {
        expect($this->payment->isAvailable(null))->toBeFalse();
    });
});

describe('Gift Card Payment Method Configuration', function () {
    beforeEach(function () {
        $this->payment = Mage::getModel('giftcard/payment');
    });

    test('returns authorize_capture as payment action', function () {
        expect($this->payment->getConfigPaymentAction())
            ->toBe(Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE);
    });

    test('can be used for zero total', function () {
        expect($this->payment->canUseForZeroTotal())->toBeTrue();
    });

    test('can capture', function () {
        expect($this->payment->canCapture())->toBeTrue();
    });

    test('can refund', function () {
        expect($this->payment->canRefund())->toBeTrue();
    });

    test('cannot authorize separately', function () {
        expect($this->payment->canAuthorize())->toBeFalse();
    });

    test('can be used in checkout', function () {
        expect($this->payment->canUseCheckout())->toBeTrue();
    });

    test('can be used internally (admin)', function () {
        expect($this->payment->canUseInternal())->toBeTrue();
    });
});

describe('Gift Card Payment Method Operations', function () {
    beforeEach(function () {
        $this->payment = Mage::getModel('giftcard/payment');
    });

    test('capture returns self without error', function () {
        $payment = new Maho\DataObject();
        $result = $this->payment->capture($payment, 100.00);

        expect($result)->toBe($this->payment);
    });

    test('authorize returns self without error', function () {
        $payment = new Maho\DataObject();
        $result = $this->payment->authorize($payment, 100.00);

        expect($result)->toBe($this->payment);
    });

    test('refund returns self without error', function () {
        $payment = new Maho\DataObject();
        $result = $this->payment->refund($payment, 50.00);

        expect($result)->toBe($this->payment);
    });
});

describe('Gift Card Payment Order Status', function () {
    test('config specifies processing as order status', function () {
        $status = Mage::getStoreConfig('payment/giftcard/order_status');

        expect($status)->toBe('processing');
    });

    test('config specifies authorize_capture as payment action', function () {
        $action = Mage::getStoreConfig('payment/giftcard/payment_action');

        expect($action)->toBe('authorize_capture');
    });
});
