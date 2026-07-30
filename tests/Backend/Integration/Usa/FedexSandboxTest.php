<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

use Tests\FedexSandbox;

uses(Tests\MahoBackendTestCase::class)->group('backend', 'fedex', 'sandbox');

/**
 * FedEx sandbox codes that come back at random for byte-identical payloads. Observed
 * roughly one call in three, on both the rate and comprehensive-rate endpoints.
 */
const FEDEX_TRANSIENT_ERROR_CODES = ['SERVICE.UNAVAILABLE.ERROR', 'SYSTEM.UNEXPECTED.ERROR'];

/**
 * Retry a sandbox call past those transients so the suite does not flake.
 *
 * Every other error still surfaces on the first attempt, and if the sandbox is genuinely
 * down the retries run out and the assertion fails as it should.
 */
function fedexRetryTransient(callable $call, callable $errorCodeOf, int $attempts = 8): mixed
{
    for ($attempt = 1; ; $attempt++) {
        $result = $call();
        if (!in_array($errorCodeOf($result), FEDEX_TRANSIENT_ERROR_CODES, true) || $attempt >= $attempts) {
            return $result;
        }
        // Back off a little; hammering the sandbox instantly tends to return the same fault.
        usleep(500_000 * $attempt);
    }
}

beforeEach(function () {
    if (!FedexSandbox::isConfigured()) {
        test()->markTestSkipped('FedEx sandbox credentials not set (FEDEX_SANDBOX_CLIENT_ID/SECRET)');
    }

    $this->oauth = new Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient(
        FedexSandbox::clientId(),
        FedexSandbox::clientSecret(),
        Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::BASE_URL_SANDBOX,
    );
    $this->client = new Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient(
        $this->oauth,
        true,
        false,
        FedexSandbox::rateEndpoint(),
    );
});

it('exchanges the client credentials for a bearer token', function () {
    $token = $this->oauth->getAccessToken();

    expect($token)->toBeString()->not->toBeEmpty();
    // FedEx issues a JWT; asserting the shape catches an endpoint that answers 200 with junk.
    expect(substr_count($token, '.'))->toBe(2);
});

it('serves the second token request from the cache', function () {
    Mage::app()->getCache()->clean('matchingAnyTag', [Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient::CACHE_TAG]);

    $first = $this->oauth->getAccessToken();
    $second = $this->oauth->getAccessToken();

    expect($second)->toBe($first);
});

it('tracks a known sandbox tracking number', function () {
    $response = $this->client->track('122816215025810');

    expect(Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::extractErrorMessage($response))->toBeNull();

    $trackResult = $response['output']['completeTrackResults'][0]['trackResults'][0];

    expect($trackResult['latestStatusDetail']['statusByLocale'])->toBeString()->not->toBeEmpty();
    expect($trackResult['scanEvents'])->toBeArray()->not->toBeEmpty();
});

it('renders a sandbox track response through the carrier', function () {
    $carrier = Mage::getModel('usa/shipping_carrier_fedex');
    Mage::app()->getStore()->setConfig('carriers/fedex/client_id', FedexSandbox::clientId());
    Mage::app()->getStore()->setConfig('carriers/fedex/client_secret', FedexSandbox::clientSecret());
    Mage::app()->getStore()->setConfig('carriers/fedex/account', FedexSandbox::account());
    Mage::app()->getStore()->setConfig('carriers/fedex/sandbox_mode', '1');

    $result = $carrier->getTracking('122816215025810');
    $tracking = $result->getAllTrackings()[0];

    expect($tracking)->not->toBeInstanceOf(Mage_Shipping_Model_Tracking_Result_Error::class);
    expect($tracking->getAllData()['status'])->toBeString()->not->toBeEmpty();
    expect($tracking->getAllData()['progressdetail'])->toBeArray()->not->toBeEmpty();
});

it('quotes domestic rates for a US shipment', function () {
    $payload = [
        'accountNumber' => ['value' => FedexSandbox::account()],
        'rateRequestControlParameters' => ['returnTransitTimes' => false],
        'requestedShipment' => [
            'shipper' => ['address' => ['postalCode' => '38017', 'countryCode' => 'US']],
            'recipient' => ['address' => ['postalCode' => '90210', 'countryCode' => 'US']],
            'shipDateStamp' => Mage_Core_Model_Locale::todayUtc(),
            'pickupType' => 'USE_SCHEDULED_PICKUP',
            'packagingType' => 'YOUR_PACKAGING',
            'rateRequestType' => ['ACCOUNT', 'LIST'],
            'totalPackageCount' => 1,
            'requestedPackageLineItems' => [
                ['groupPackageCount' => 1, 'weight' => ['units' => 'LB', 'value' => 10]],
            ],
        ],
    ];

    $response = fedexRetryTransient(
        fn() => $this->client->getRates($payload),
        fn(array $r) => $r['errors'][0]['code'] ?? null,
    );

    expect(Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::extractErrorMessage($response))->toBeNull();
    expect($response['output']['rateReplyDetails'])->toBeArray()->not->toBeEmpty();

    $reply = $response['output']['rateReplyDetails'][0];

    expect($reply)->toHaveKey('serviceType');
    expect($reply['ratedShipmentDetails'][0])->toHaveKey('rateType');
});

it('collects priced rates through the carrier, as checkout does', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('carriers/fedex/active', '1');
    $store->setConfig('carriers/fedex/client_id', FedexSandbox::clientId());
    $store->setConfig('carriers/fedex/client_secret', FedexSandbox::clientSecret());
    $store->setConfig('carriers/fedex/account', FedexSandbox::account());
    $store->setConfig('carriers/fedex/sandbox_mode', '1');
    $store->setConfig('carriers/fedex/rate_endpoint', FedexSandbox::rateEndpoint());
    $store->setConfig('shipping/origin/country_id', 'US');
    $store->setConfig('shipping/origin/postcode', '38017');
    $store->setConfig(
        'carriers/fedex/allowed_methods',
        'FEDEX_GROUND,FEDEX_EXPRESS_SAVER,FEDEX_2_DAY,FEDEX_2_DAY_AM,'
        . 'STANDARD_OVERNIGHT,PRIORITY_OVERNIGHT,FIRST_OVERNIGHT',
    );

    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setStoreId(1);
    $request->setDestCountryId('US');
    $request->setDestPostcode('90210');
    $request->setPackageWeight(10);
    $request->setFreeMethodWeight(10);
    $request->setPackageValue(100);
    $request->setPackagePhysicalValue(100);
    $request->setPackageValueWithDiscount(100);
    $request->setPackageQty(1);

    // Rate results carry no FedEx error code, so retry while every rate is an error object.
    $rates = fedexRetryTransient(
        fn() => Mage::getModel('usa/shipping_carrier_fedex')->collectRates($request)->getAllRates(),
        fn(array $rates) => array_filter($rates, fn($r) => !$r instanceof Mage_Shipping_Model_Rate_Result_Error)
            ? null
            : FEDEX_TRANSIENT_ERROR_CODES[0],
    );

    expect($rates)->not->toBeEmpty();
    // An error result here means the quote failed; the storefront would silently show
    // no FedEx option, so this must fail the suite rather than pass with zero rates.
    foreach ($rates as $rate) {
        expect($rate)->not->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Error::class);
    }
    expect($rates[0]->getMethod())->toBeString()->not->toBeEmpty();
    expect((float) $rates[0]->getPrice())->toBeGreaterThan(0.0);
});
