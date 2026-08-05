<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Helpers\FrankfurterHarness;

uses(Tests\MahoBackendTestCase::class);

function frankfurterClient(array &$requests, string $body): MockHttpClient
{
    return new MockHttpClient(function (string $method, string $url) use (&$requests, $body): MockResponse {
        $requests[] = $url;
        return new MockResponse($body);
    });
}

function frankfurterRates(array $rates): string
{
    $quotes = [];
    foreach ($rates as $quote => $rate) {
        $quotes[] = ['date' => '2026-08-05', 'base' => 'EUR', 'quote' => $quote, 'rate' => $rate];
    }

    return json_encode($quotes);
}

describe('Frankfurter currency import', function () {
    beforeEach(function () {
        $this->requests = [];
    });

    it('quotes every allowed currency against EUR in a single request', function () {
        $importer = (new FrankfurterHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(frankfurterClient($this->requests, frankfurterRates(['GBP' => 0.85, 'USD' => 1.10])));

        $importer->fetchRates();

        expect($this->requests)->toHaveCount(1);

        $url = $this->requests[0];
        expect($url)->toStartWith('https://api.frankfurter.dev/v2/rates');
        expect($url)->not->toContain('access_key');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        expect($query['base'])->toBe('EUR');
        expect(explode(',', $query['quotes']))->toEqualCanonicalizing(['EUR', 'GBP', 'USD']);
    });

    it('derives cross-rates from the flat quote list', function () {
        $importer = (new FrankfurterHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(frankfurterClient(
                $this->requests,
                frankfurterRates(['EUR' => 1.0, 'GBP' => 0.85, 'USD' => 1.10]),
            ));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect($rates['EUR']['USD'])->toEqualWithDelta(1.10, 0.000001);
        expect($rates['EUR']['EUR'])->toEqual(1);
        expect($rates['USD']['GBP'])->toEqualWithDelta(0.85 / 1.10, 0.000001);
        expect($rates['USD']['EUR'])->toEqualWithDelta(1 / 1.10, 0.000001);
    });

    it('passes the service error through, since v2 rejects the whole request over one bad code', function () {
        $importer = (new FrankfurterHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(frankfurterClient(
                $this->requests,
                json_encode(['status' => 422, 'message' => 'invalid currency: XXX']),
            ));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rate service error: invalid currency: XXX']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('flags only the currencies the service left out', function () {
        $importer = (new FrankfurterHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['USD'])
            ->setHttpClient(frankfurterClient($this->requests, frankfurterRates(['USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($rates['USD']['EUR'])->toEqualWithDelta(1 / 1.10, 0.000001);
        expect($rates['USD']['GBP'])->toBeNull();
        expect($importer->getMessages())->toBe(['Unable to calculate rate for USD to GBP.']);
    });

    it('does not call the service for a EUR-only store', function () {
        $importer = (new FrankfurterHarness())
            ->setCurrencies(['EUR'], ['EUR'])
            ->setHttpClient(frankfurterClient($this->requests, frankfurterRates([])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe([]);
        expect($importer->getMessages())->toBe([]);
        expect($rates)->toBe(['EUR' => ['EUR' => 1]]);
    });
});
