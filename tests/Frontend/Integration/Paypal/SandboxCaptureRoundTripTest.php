<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\PaypalSandbox;

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal', 'sandbox');

beforeEach(function () {
    if (!PaypalSandbox::isConfigured()) {
        test()->markTestSkipped('PayPal sandbox credentials not set (PAYPAL_SANDBOX_CLIENT_ID/SECRET)');
    }
});

/**
 * End-to-end proof against the real PayPal sandbox that a non-final partial capture keeps the
 * authorization open for a second capture. This is the actual behaviour CaptureFinalizationTest
 * relies on when it only asserts the request body Maho builds.
 *
 * A server-side authorization requires an approved order without a buyer redirect, which is only
 * possible via the card (ACDC) flow. If the sandbox account cannot produce a card authorization
 * (ACDC disabled, 3DS required, card declined), the test skips instead of failing.
 */
it('keeps the authorization open for a second capture when final_capture is false', function () {
    $client = Mage::getModel('paypal/api_client')
        ->setExplicitCredentials(PaypalSandbox::clientId(), PaypalSandbox::clientSecret(), true);

    $orderBody = [
        'intent' => 'AUTHORIZE',
        'purchase_units' => [[
            'amount' => ['currency_code' => 'USD', 'value' => '100.00'],
        ]],
        'payment_source' => ['card' => [
            'number' => '4111111111111111',
            'expiry' => '2030-01',
            'security_code' => '123',
        ]],
    ];

    try {
        $order = $client->createOrder(['body' => $orderBody]);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Sandbox could not create a card order (ACDC likely disabled): ' . $e->getMessage());
    }

    // Resolve an authorization id, driving the order to AUTHORIZED if it is only APPROVED.
    $authId = $order['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
    if (!$authId && ($order['status'] ?? '') === 'APPROVED') {
        try {
            $authResult = $client->authorizeOrder($order['id']);
            $authId = $authResult['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
        } catch (\Throwable $e) {
            test()->markTestSkipped('Sandbox could not authorize the card order: ' . $e->getMessage());
        }
    }
    if (!$authId) {
        test()->markTestSkipped('Sandbox returned no authorization. Response: ' . substr(json_encode($order), 0, 800));
    }

    // First capture: 40 of 100, explicitly NOT final -> the authorization must stay open.
    // Sandbox card captures often settle asynchronously (COMPLETED or PENDING); either way the
    // capture was accepted as long as it carries an id.
    $first = $client->captureAuthorization($authId, [
        'amount' => ['value' => '40.00', 'currency_code' => 'USD'],
        'final_capture' => false,
    ]);
    expect($first['id'] ?? null)->not->toBeNull();
    expect($first['status'] ?? null)->toBeIn(['COMPLETED', 'PENDING']);

    // The authorization is only partially consumed, not closed. This is exactly the state the
    // buggy final_capture=true would have destroyed.
    $auth = $client->getAuthorization($authId);
    expect($auth['status'] ?? null)->not->toBe('CAPTURED');
    expect($auth['status'] ?? null)->not->toBe('VOIDED');

    // The decisive assertion: a second capture against the still-open authorization succeeds.
    // Under the old final_capture=true behaviour the authorization would already be closed and
    // this call would fail.
    $second = $client->captureAuthorization($authId, [
        'amount' => ['value' => '60.00', 'currency_code' => 'USD'],
        'final_capture' => true,
    ]);
    expect($second['id'] ?? null)->not->toBeNull();
    expect($second['status'] ?? null)->toBeIn(['COMPLETED', 'PENDING']);
});
