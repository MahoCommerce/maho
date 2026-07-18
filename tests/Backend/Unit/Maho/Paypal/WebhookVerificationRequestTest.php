<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

const WEBHOOK_TEST_HEADERS = [
    'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
    'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-360caa42',
    'PAYPAL-TRANSMISSION-ID' => '01c539a3-82b5-11f1-966b-d7607e33a153',
    'PAYPAL-TRANSMISSION-SIG' => 'dGVzdC1zaWduYXR1cmU=',
    'PAYPAL-TRANSMISSION-TIME' => '2026-07-18T14:02:47Z',
];

it('splices the raw webhook body into the verify request verbatim', function () {
    $rawBody = '{"id":"WH-1","event_type":"CHECKOUT.ORDER.APPROVED","resource":{"amount":{"value":"13.64","breakdown":{}}}}';

    $request = Maho_Paypal_Model_Api_Client::buildVerificationRequest(WEBHOOK_TEST_HEADERS, 'WEBHOOK-ID-1', $rawBody);

    expect($request)->toEndWith(',"webhook_event":' . $rawBody . '}');
});

it('preserves empty JSON objects that a decode/encode round-trip would fold into arrays', function () {
    $rawBody = '{"resource":{"breakdown":{},"supplementary_data":{}}}';

    // The failure mode this guards against: PHP's associative decode turns {} into [].
    expect(json_encode(json_decode($rawBody, true)))->not->toBe($rawBody);

    $request = Maho_Paypal_Model_Api_Client::buildVerificationRequest(WEBHOOK_TEST_HEADERS, 'WEBHOOK-ID-1', $rawBody);

    expect($request)->toContain('"breakdown":{}')
        ->and($request)->not->toContain('"breakdown":[]');
});

it('produces valid JSON carrying the metadata fields and the untouched event', function () {
    $rawBody = '{"id":"WH-1","links":[{"href":"https://api.sandbox.paypal.com/v1/notifications"}]}';

    $request = Maho_Paypal_Model_Api_Client::buildVerificationRequest(WEBHOOK_TEST_HEADERS, 'WEBHOOK-ID-1', $rawBody);

    expect(json_validate($request))->toBeTrue();

    $decoded = json_decode($request, true);
    expect($decoded['auth_algo'])->toBe('SHA256withRSA')
        ->and($decoded['cert_url'])->toBe(WEBHOOK_TEST_HEADERS['PAYPAL-CERT-URL'])
        ->and($decoded['transmission_id'])->toBe(WEBHOOK_TEST_HEADERS['PAYPAL-TRANSMISSION-ID'])
        ->and($decoded['transmission_sig'])->toBe(WEBHOOK_TEST_HEADERS['PAYPAL-TRANSMISSION-SIG'])
        ->and($decoded['transmission_time'])->toBe(WEBHOOK_TEST_HEADERS['PAYPAL-TRANSMISSION-TIME'])
        ->and($decoded['webhook_id'])->toBe('WEBHOOK-ID-1')
        ->and($decoded['webhook_event'])->toBe(json_decode($rawBody, true));
});

it('escapes metadata values that contain JSON-significant characters', function () {
    $headers = ['PAYPAL-AUTH-ALGO' => 'algo"with\quotes'] + WEBHOOK_TEST_HEADERS;

    $request = Maho_Paypal_Model_Api_Client::buildVerificationRequest($headers, 'WEBHOOK-ID-1', '{}');

    expect(json_validate($request))->toBeTrue()
        ->and(json_decode($request, true)['auth_algo'])->toBe('algo"with\quotes');
});

it('treats missing transmission headers as empty strings', function () {
    $request = Maho_Paypal_Model_Api_Client::buildVerificationRequest([], 'WEBHOOK-ID-1', '{}');

    $decoded = json_decode($request, true);
    expect(json_validate($request))->toBeTrue()
        ->and($decoded['auth_algo'])->toBe('')
        ->and($decoded['cert_url'])->toBe('')
        ->and($decoded['webhook_event'])->toBe([]);
});
