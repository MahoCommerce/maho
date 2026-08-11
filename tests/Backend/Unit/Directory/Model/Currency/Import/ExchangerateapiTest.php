<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Helpers\ExchangerateapiHarness;

uses(Tests\MahoBackendTestCase::class);

function erapiClient(array &$requests, string $body): MockHttpClient
{
    return new MockHttpClient(function (string $method, string $url) use (&$requests, $body): MockResponse {
        $requests[] = $url;
        return new MockResponse($body);
    });
}

function erapiRates(array $rates): string
{
    return json_encode(['result' => 'success', 'base_code' => 'EUR', 'rates' => $rates]);
}

describe('ExchangeRate-API currency import', function () {
    beforeEach(function () {
        $this->requests = [];
    });

    it('asks the open EUR endpoint once, without credentials', function () {
        $importer = (new ExchangerateapiHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(erapiClient($this->requests, erapiRates(['EUR' => 1, 'GBP' => 0.85, 'USD' => 1.10])));

        $importer->fetchRates();

        expect($this->requests)->toBe(['https://open.er-api.com/v6/latest/EUR']);
    });

    it('derives cross-rates for each base currency', function () {
        $importer = (new ExchangerateapiHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(erapiClient($this->requests, erapiRates(['EUR' => 1, 'GBP' => 0.85, 'USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect($rates['EUR']['USD'])->toEqualWithDelta(1.10, 0.000001);
        expect($rates['USD']['GBP'])->toEqualWithDelta(0.85 / 1.10, 0.000001);
        expect($rates['USD']['USD'])->toEqual(1);
    });

    it('ignores the currencies the store does not use', function () {
        $importer = (new ExchangerateapiHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(erapiClient($this->requests, erapiRates(['EUR' => 1, 'USD' => 1.10, 'JPY' => 170.0])));

        $rates = $importer->fetchRates();

        expect(array_keys($rates['USD']))->toBe(['EUR', 'USD']);
        expect($importer->getMessages())->toBe([]);
    });

    it('reports an error result instead of writing rates', function () {
        $importer = (new ExchangerateapiHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(erapiClient(
                $this->requests,
                json_encode(['result' => 'error', 'error-type' => 'unsupported-code']),
            ));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(["Currency rates can't be retrieved."]);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('flags only the currencies the service left out', function () {
        $importer = (new ExchangerateapiHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['USD'])
            ->setHttpClient(erapiClient($this->requests, erapiRates(['EUR' => 1, 'USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($rates['USD']['GBP'])->toBeNull();
        expect($importer->getMessages())->toBe(['Unable to calculate rate for USD to GBP.']);
    });
});
