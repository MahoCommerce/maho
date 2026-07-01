<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

/**
 * Records the body passed to captureAuthorization() so the finalization logic can be asserted
 * without hitting the PayPal API.
 */
function makeRecordingClient(): Maho_Paypal_Model_Api_Client
{
    return new class extends Maho_Paypal_Model_Api_Client {
        public bool $called = false;
        public array $capturedBody = [];

        #[\Override]
        public function captureAuthorization(string $authorizationId, array $body = []): array
        {
            $this->called = true;
            $this->capturedBody = $body;
            return ['id' => 'FAKE-CAPTURE-1'];
        }
    };
}

/**
 * Build a PayPal method with an injected recording client and a payment whose order has the
 * given grand total and canInvoice() result. Returns [method, payment, recordingClient].
 *
 * canInvoice() stands in for "are there still items left to invoice after this one": the invoice
 * items are already registered when capture() runs, so it is false exactly on the final invoice.
 */
function makeCaptureFixture(float $grandTotal, bool $canInvoice): array
{
    $order = new class extends Mage_Sales_Model_Order {
        public bool $stubCanInvoice = true;
        #[\Override]
        public function canInvoice()
        {
            return $this->stubCanInvoice;
        }
    };
    $order->stubCanInvoice = $canInvoice;
    $order->setBaseGrandTotal($grandTotal)->setBaseCurrencyCode('EUR');

    $payment = Mage::getModel('sales/order_payment');
    $payment->setOrder($order);
    $payment->setAdditionalInformation('paypal_authorization_id', 'AUTH-123');

    $method = Mage::getModel('paypal/method_standardCheckout');
    $client = makeRecordingClient();

    $ref = new ReflectionProperty(Maho_Paypal_Model_Method_Abstract::class, '_apiClient');
    $ref->setAccessible(true);
    $ref->setValue($method, $client);

    return [$method, $payment, $client];
}

it('captures the full authorization with an empty body on a full single invoice', function () {
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);

    $method->capture($payment, 100.0);

    // amount == grand total -> no explicit amount, PayPal captures the whole authorization
    expect($client->called)->toBeTrue();
    expect($client->capturedBody)->toBe([]);
});

it('keeps the authorization open on the first of several partial invoices', function () {
    // 40 of 100 invoiced now, items still left -> canInvoice() stays true
    [$method, $payment, $client] = makeCaptureFixture(100.0, true);

    $method->capture($payment, 40.0);

    expect($client->capturedBody['amount']['value'])->toBe('40.00');
    expect($client->capturedBody['amount']['currency_code'])->toBe('EUR');
    expect($client->capturedBody['final_capture'])->toBeFalse();
});

// The two cases below capture the SAME amount (60) and differ only in whether items remain.
// This proves final_capture keys on the remaining-to-invoice state, not on the amount: the
// buggy implementation set final_capture=true for both and would fail the first of the pair.
it('keeps the authorization open on a mid-sequence partial invoice', function () {
    [$method, $payment, $client] = makeCaptureFixture(100.0, true);

    $method->capture($payment, 60.0);

    expect($client->capturedBody['final_capture'])->toBeFalse();
});

it('closes the authorization on the last partial invoice', function () {
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);

    $method->capture($payment, 60.0);

    expect($client->capturedBody['amount']['value'])->toBe('60.00');
    expect($client->capturedBody['final_capture'])->toBeTrue();
});

it('releases the remainder on a reduced final invoice (discount)', function () {
    // full items invoiced but at a reduced amount -> final capture that releases the rest
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);

    $method->capture($payment, 90.0);

    expect($client->capturedBody['amount']['value'])->toBe('90.00');
    expect($client->capturedBody['final_capture'])->toBeTrue();
});
