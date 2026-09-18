<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

function makePlacementClient(array $order): Maho_Paypal_Model_Api_Client
{
    return new class ($order) extends Maho_Paypal_Model_Api_Client {
        public array $calls = [];

        public function __construct(public array $order)
        {
            parent::__construct();
        }

        #[\Override]
        public function getOrder(string $orderId, ?string $fields = null): array
        {
            $this->calls[] = 'get';
            return $this->order;
        }

        #[\Override]
        public function authorizeOrder(string $orderId): array
        {
            $this->calls[] = 'authorize';
            return ['id' => $orderId, 'status' => 'COMPLETED', 'purchase_units' => [
                ['payments' => ['authorizations' => [['id' => 'AUTH-NEW']]]],
            ]];
        }

        #[\Override]
        public function captureOrder(string $orderId): array
        {
            $this->calls[] = 'capture';
            return ['id' => $orderId, 'status' => 'COMPLETED', 'purchase_units' => [
                ['payments' => ['captures' => [['id' => 'CAP-NEW']]]],
            ]];
        }
    };
}

function makePaypalOrder(string $status, array $payments = [], array $overrides = []): array
{
    return ['id' => 'PPORDER1', 'status' => $status, 'purchase_units' => [array_merge([
        'invoice_id' => '100000123',
        'amount' => ['currency_code' => 'EUR', 'value' => '100.00'],
        'payments' => $payments,
    ], $overrides)]];
}

/** @return array{0: Maho_Paypal_Model_Method_StandardCheckout, 1: Mage_Sales_Model_Order_Payment, 2: Maho_Paypal_Model_Api_Client, 3: \Maho\DataObject} */
function makePlacementFixture(array $paypalOrder): array
{
    $order = new class extends Mage_Sales_Model_Order {
        #[\Override]
        public function canInvoice()
        {
            return false;
        }
    };
    $order->setIncrementId('100000123')->setBaseGrandTotal(100.0)->setBaseCurrencyCode('EUR');

    $payment = Mage::getModel('sales/order_payment');
    $payment->setOrder($order);
    $payment->setAdditionalInformation('paypal_order_id', 'PPORDER1');

    $method = Mage::getModel('paypal/method_standardCheckout');
    $method->setInfoInstance($payment);
    $client = makePlacementClient($paypalOrder);
    $method->setApiClient($client);

    return [$method, $payment, $client, new \Maho\DataObject()];
}

it('authorizes an approved PayPal order during placement', function () {
    [$method, $payment, $client, $state] = makePlacementFixture(makePaypalOrder('APPROVED'));

    $method->initialize('authorize', $state);

    expect($client->calls)->toBe(['get', 'authorize']);
    expect($payment->getAdditionalInformation('paypal_authorization_id'))->toBe('AUTH-NEW');
    expect($payment->getTransactionId())->toBe('AUTH-NEW');
    expect($state->getState())->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
});

it('captures an approved PayPal order during placement when the action is capture', function () {
    [$method, $payment, $client, $state] = makePlacementFixture(makePaypalOrder('APPROVED'));

    $method->initialize('capture', $state);

    expect($client->calls)->toBe(['get', 'capture']);
    expect($payment->getAdditionalInformation('paypal_capture_id'))->toBe('CAP-NEW');
    expect($state->getState())->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
});

it('reuses the existing authorization of a completed PayPal order', function () {
    $paypalOrder = makePaypalOrder('COMPLETED', ['authorizations' => [['id' => 'AUTH-OLD']]]);
    [$method, $payment, $client, $state] = makePlacementFixture($paypalOrder);

    $method->initialize('authorize', $state);

    expect($client->calls)->toBe(['get']);
    expect($payment->getAdditionalInformation('paypal_authorization_id'))->toBe('AUTH-OLD');
    expect($state->getState())->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
});

it('refuses to place an order on a PayPal order the buyer has not approved', function () {
    [$method, , $client, $state] = makePlacementFixture(makePaypalOrder('CREATED'));

    expect(fn() => $method->initialize('authorize', $state))
        ->toThrow(Mage_Core_Exception::class, 'Please complete the PayPal payment');
    expect($client->calls)->toBe(['get']);
    expect($state->getState())->toBeNull();
});

it('refuses a PayPal order created for a different order', function () {
    [$method, , $client, $state] = makePlacementFixture(makePaypalOrder('APPROVED', [], ['invoice_id' => '100000999']));

    expect(fn() => $method->initialize('authorize', $state))
        ->toThrow(Mage_Core_Exception::class, 'does not belong to this cart');
    expect($client->calls)->toBe(['get']);
});

it('refuses a PayPal order whose amount no longer matches the order', function () {
    $paypalOrder = makePaypalOrder('APPROVED', [], ['amount' => ['currency_code' => 'EUR', 'value' => '80.00']]);
    [$method, , $client, $state] = makePlacementFixture($paypalOrder);

    expect(fn() => $method->initialize('capture', $state))
        ->toThrow(Mage_Core_Exception::class, 'amount does not match');
    expect($client->calls)->toBe(['get']);
});

it('does not call PayPal when the payment already carries a capture id', function () {
    [$method, $payment, $client, $state] = makePlacementFixture(makePaypalOrder('COMPLETED'));
    $payment->setAdditionalInformation('paypal_capture_id', 'CAP-DONE');

    $method->initialize('capture', $state);

    expect($client->calls)->toBe([]);
    expect($payment->getTransactionId())->toBe('CAP-DONE');
    expect($state->getState())->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
});
