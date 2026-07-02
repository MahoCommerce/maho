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
        public int $calls = 0;
        public array $capturedBody = [];

        #[\Override]
        public function captureAuthorization(string $authorizationId, array $body = []): array
        {
            $this->calls++;
            $this->capturedBody = $body;
            return ['id' => 'FAKE-CAPTURE-' . $this->calls];
        }
    };
}

/**
 * Build a PayPal method with an injected recording client and a payment whose order has the
 * given grand total and canInvoice() result. Returns [method, payment, recordingClient].
 *
 * canInvoice() stands in for "are there still items left to invoice": items are registered
 * when an invoice is CREATED (not captured), so it is false as soon as every item is on an
 * invoice — even one still awaiting capture. Tests that need such pending invoices attach
 * them via $order->stubInvoices and mark the one being captured with $payment->setInvoice().
 */
function makeCaptureFixture(float $grandTotal, bool $canInvoice): array
{
    $order = new class extends Mage_Sales_Model_Order {
        public bool $stubCanInvoice = true;
        /** @var Mage_Sales_Model_Order_Invoice[] */
        public array $stubInvoices = [];

        #[\Override]
        public function canInvoice()
        {
            return $this->stubCanInvoice;
        }

        #[\Override]
        public function getInvoiceCollection()
        {
            return $this->stubInvoices;
        }
    };
    $order->stubCanInvoice = $canInvoice;
    $order->setBaseGrandTotal($grandTotal)->setBaseCurrencyCode('EUR');

    $payment = Mage::getModel('sales/order_payment');
    $payment->setOrder($order);
    $payment->setAdditionalInformation('paypal_authorization_id', 'AUTH-123');

    $method = Mage::getModel('paypal/method_standardCheckout');
    $client = makeRecordingClient();
    $method->setApiClient($client);

    return [$method, $payment, $client];
}

function makeInvoiceStub(?int $id, int $state = Mage_Sales_Model_Order_Invoice::STATE_OPEN): Mage_Sales_Model_Order_Invoice
{
    $invoice = Mage::getModel('sales/order_invoice')->setState($state);
    if ($id !== null) {
        $invoice->setId($id);
    }
    return $invoice;
}

it('captures the full authorization with an empty body on a full single invoice', function () {
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);

    $method->capture($payment, 100.0);

    // amount == grand total -> no explicit amount, PayPal captures the whole authorization
    expect($client->calls)->toBe(1);
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

// Invoices created with "Not Capture" register their items immediately but are captured later,
// so canInvoice() alone cannot tell "last capture" from "last invoice". The cases below cover
// captures that run while such pending invoices exist.
it('keeps the authorization open when another pending invoice awaits capture', function () {
    // two "Not Capture" invoices cover all items (canInvoice() false); capturing the first
    // must NOT finalize, or the second capture fails against a closed authorization
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);
    $current = makeInvoiceStub(10);
    $payment->getOrder()->stubInvoices = [$current, makeInvoiceStub(11)];
    $payment->setInvoice($current);

    $method->capture($payment, 60.0);

    expect($client->capturedBody['final_capture'])->toBeFalse();
});

it('closes the authorization when capturing the last pending invoice', function () {
    // the other invoice is already paid -> this capture is the last one, finalize
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);
    $current = makeInvoiceStub(10);
    $payment->getOrder()->stubInvoices = [
        $current,
        makeInvoiceStub(11, Mage_Sales_Model_Order_Invoice::STATE_PAID),
    ];
    $payment->setInvoice($current);

    $method->capture($payment, 60.0);

    expect($client->capturedBody['final_capture'])->toBeTrue();
});

it('keeps the authorization open on a fresh final invoice while another invoice is pending capture', function () {
    // the invoice being captured is new (unsaved, no id) and covers the remaining items,
    // but a previously created "Not Capture" invoice still awaits its own capture
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);
    $payment->getOrder()->stubInvoices = [makeInvoiceStub(11)];
    $payment->setInvoice(makeInvoiceStub(null));

    $method->capture($payment, 40.0);

    expect($client->capturedBody['final_capture'])->toBeFalse();
});

it('derives finality from the invoice being captured when driven through the payment capture flow', function () {
    // through the real Mage_Sales_Model_Order_Payment::capture(): the payment must learn which
    // invoice is being captured, so the pending sibling invoice keeps final_capture false even
    // though canInvoice() is already false
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);
    $order = $payment->getOrder();
    $order->setStoreId(1);

    $current = makeInvoiceStub(10)->setOrder($order)->setBaseGrandTotal(60.0);
    $order->stubInvoices = [$current, makeInvoiceStub(11)];

    $payment->setMethodInstance($method);
    $method->setInfoInstance($payment);

    $payment->capture($current);

    expect($client->calls)->toBe(1);
    expect($client->capturedBody['final_capture'])->toBeFalse();
});

/**
 * Create a persisted two-item (60 + 40 = 100) PayPal order, so invoices flow through the real
 * register()/capture() machinery instead of stubs.
 */
function makePersistedPaypalOrder(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
        ->setStatus('processing')
        ->setCustomerIsGuest(1)
        ->setCustomerEmail('paypal-capture-e2e@example.com')
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setStoreToOrderRate(1)
        ->setBaseToOrderRate(1)
        ->setSubtotal(100)->setBaseSubtotal(100)
        ->setGrandTotal(100)->setBaseGrandTotal(100)
        ->setTotalQtyOrdered(2);

    foreach ([['sku' => 'E2E-A', 'price' => 60.0], ['sku' => 'E2E-B', 'price' => 40.0]] as $row) {
        $item = Mage::getModel('sales/order_item');
        $item->setStoreId(1)
            ->setProductType('simple')
            ->setSku($row['sku'])
            ->setName($row['sku'])
            ->setQtyOrdered(1)
            ->setPrice($row['price'])->setBasePrice($row['price'])
            ->setOriginalPrice($row['price'])
            ->setRowTotal($row['price'])->setBaseRowTotal($row['price']);
        $order->addItem($item);
    }

    $payment = Mage::getModel('sales/order_payment');
    $payment->setMethod('paypal_standard_checkout');
    $payment->setAdditionalInformation('paypal_authorization_id', 'AUTH-E2E');
    $order->setPayment($payment);

    $order->save();
    return $order;
}

/**
 * Create a "Not Capture" invoice for a single order item through the real service layer:
 * items get registered (consuming qty_to_invoice) but the invoice stays open awaiting capture.
 */
function makePendingInvoice(Mage_Sales_Model_Order $order, string $sku): Mage_Sales_Model_Order_Invoice
{
    $itemId = null;
    foreach ($order->getAllItems() as $item) {
        if ($item->getSku() === $sku) {
            $itemId = (int) $item->getId();
        }
    }
    $invoice = $order->prepareInvoice([$itemId => 1]);
    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE);
    $invoice->register();
    // persist invoice + order atomically, exactly as InvoiceController::_saveInvoice() does
    $order->setIsInProcess(true);
    Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();
    return $invoice;
}

/**
 * Capture one invoice of the given order through the full real chain
 * (Invoice::capture() -> Payment::capture() -> method capture()) on freshly loaded models,
 * exactly as the admin captureAction does. Returns the body sent to PayPal.
 */
function captureInvoiceOnline(int $orderId, int $invoiceId): array
{
    $order = Mage::getModel('sales/order')->load($orderId);
    $payment = $order->getPayment();
    $method = Mage::getModel('paypal/method_standardCheckout');
    $client = makeRecordingClient();
    $method->setApiClient($client);
    $method->setInfoInstance($payment);
    $payment->setMethodInstance($method);

    $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
    $invoice->setOrder($order);
    $invoice->capture();
    $order->setIsInProcess(true);
    Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();

    expect($client->calls)->toBe(1);
    return $client->capturedBody;
}

it('keeps the authorization open across two pre-registered pending invoices on a real order', function () {
    $order = makePersistedPaypalOrder();

    // both "Not Capture" invoices exist before any capture: every item is registered,
    // so canInvoice() is already false while two captures are still to come
    $invoiceA = makePendingInvoice($order, 'E2E-A');
    $invoiceB = makePendingInvoice($order, 'E2E-B');
    expect(Mage::getModel('sales/order')->load($order->getId())->canInvoice())->toBeFalse();

    $firstBody = captureInvoiceOnline((int) $order->getId(), (int) $invoiceA->getId());
    expect($firstBody['amount']['value'])->toBe('60.00');
    expect($firstBody['final_capture'])->toBeFalse();

    // the first capture really went through: invoice A is paid, B still awaits capture
    expect((int) Mage::getModel('sales/order_invoice')->load($invoiceA->getId())->getState())
        ->toBe(Mage_Sales_Model_Order_Invoice::STATE_PAID);
    expect((int) Mage::getModel('sales/order_invoice')->load($invoiceB->getId())->getState())
        ->toBe(Mage_Sales_Model_Order_Invoice::STATE_OPEN);

    $secondBody = captureInvoiceOnline((int) $order->getId(), (int) $invoiceB->getId());
    expect($secondBody['amount']['value'])->toBe('40.00');
    expect($secondBody['final_capture'])->toBeTrue();
});

it('marks the local parent authorization transaction for closing on a reduced final capture', function () {
    // PayPal releases the remainder (final_capture true) while _isCaptureFinal() sees
    // 90 != 100 and would leave the local authorization transaction open -> the method
    // must close it explicitly to keep local state in step with the gateway
    [$method, $payment, $client] = makeCaptureFixture(100.0, false);

    $method->capture($payment, 90.0);

    expect($client->capturedBody['final_capture'])->toBeTrue();
    expect($payment->getShouldCloseParentTransaction())->toBeTrue();
});

it('leaves the parent authorization transaction open on a non-final partial capture', function () {
    [$method, $payment, $client] = makeCaptureFixture(100.0, true);

    $method->capture($payment, 40.0);

    expect($client->capturedBody['final_capture'])->toBeFalse();
    expect($payment->getShouldCloseParentTransaction())->not->toBeTrue();
});
