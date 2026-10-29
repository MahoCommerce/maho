<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Helpers\AllratestodayHarness;

uses(Tests\MahoBackendTestCase::class);

function allratestodayClient(array &$requests, string $body): MockHttpClient
{
    return new MockHttpClient(function (string $method, string $url) use (&$requests, $body): MockResponse {
        $requests[] = $url;
        return new MockResponse($body);
    });
}

function allratestodayEcbTable(array $rates): string
{
    return json_encode(['result' => 'success', 'base_code' => 'EUR', 'date' => '2026-10-28', 'rates' => $rates]);
}

/**
 * @param array<array{string, string, float}> $rows [base, quote, value] = "1 base = value quote"
 */
function allratestodayBankTable(string $bank, array $rows): string
{
    $rates = [];
    foreach ($rows as [$base, $quote, $value]) {
        $rates[] = ['base' => $base, 'quote' => $quote, 'type' => 'reference', 'value' => $value];
    }

    return json_encode(['bank' => $bank, 'rate_date' => '2026-10-28', 'age_days' => 1, 'stale' => false, 'rates' => $rates]);
}

describe('AllRatesToday currency import', function () {
    beforeEach(function () {
        $this->requests = [];
    });

    it('fetches the EUR-based ECB table in a single request', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayEcbTable(['EUR' => 1, 'GBP' => 0.85, 'USD' => 1.10, 'JPY' => 160.0])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe(['https://allratestoday.com/api/open/latest/EUR']);
        expect($importer->getMessages())->toBe([]);
        expect($rates['EUR']['USD'])->toEqualWithDelta(1.10, 0.000001);
        expect($rates['EUR']['EUR'])->toEqual(1);
        expect($rates['USD']['GBP'])->toEqualWithDelta(0.85 / 1.10, 0.000001);
        expect($rates['USD'])->not->toHaveKey('JPY');
    });

    it('passes an error envelope through as a service error', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(allratestodayClient($this->requests, json_encode(['result' => 'error', 'error' => 'Unsupported base currency'])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rates can\'t be retrieved.']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('flags only the currencies the table left out', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['USD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayEcbTable(['USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($rates['USD']['EUR'])->toEqualWithDelta(1 / 1.10, 0.000001);
        expect($rates['USD']['GBP'])->toBeNull();
        expect($importer->getMessages())->toBe(['Unable to calculate rate for USD to GBP.']);
    });

    it('re-bases a central bank table quoted in the home currency on EUR', function () {
        // SAMA quotes SAR per unit of foreign currency: 1 USD = 3.75 SAR, 1 EUR = 4.5 SAR
        $importer = (new AllratestodayHarness())
            ->setBank('sama')
            ->setCurrencies(['EUR', 'SAR', 'USD'], ['SAR', 'EUR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('sama', [
                ['USD', 'SAR', 3.75],
                ['EUR', 'SAR', 4.5],
                ['GBP', 'SAR', 5.0],
            ])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe(['https://allratestoday.com/api/open/central-bank/sama']);
        expect($importer->getMessages())->toBe([]);
        expect($rates['SAR']['USD'])->toEqualWithDelta(1 / 3.75, 0.000001);
        expect($rates['SAR']['EUR'])->toEqualWithDelta(1 / 4.5, 0.000001);
        expect($rates['EUR']['SAR'])->toEqualWithDelta(4.5, 0.000001);
        expect($rates['EUR']['USD'])->toEqualWithDelta(4.5 / 3.75, 0.000001);
    });

    it('re-bases a central bank table quoted per home unit on EUR', function () {
        // RBNZ quotes foreign currency per 1 NZD: 1 NZD = 0.5 USD, 1 NZD = 0.5 EUR
        $importer = (new AllratestodayHarness())
            ->setBank('RBNZ')
            ->setCurrencies(['EUR', 'NZD', 'USD'], ['NZD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('rbnz', [
                ['NZD', 'USD', 0.5],
                ['NZD', 'EUR', 0.5],
                ['NZD', 'AUD', 0.8],
            ])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe(['https://allratestoday.com/api/open/central-bank/rbnz']);
        expect($importer->getMessages())->toBe([]);
        expect($rates['NZD']['USD'])->toEqualWithDelta(0.5, 0.000001);
        expect($rates['NZD']['EUR'])->toEqualWithDelta(0.5, 0.000001);
        expect($rates['NZD']['NZD'])->toEqual(1);
    });

    it('reports a central bank table without a EUR rate', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('sama')
            ->setCurrencies(['EUR', 'SAR', 'USD'], ['SAR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('sama', [['USD', 'SAR', 3.75]])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rate service error: central bank sama publishes no EUR rate']);
        expect($rates)->toBe(['SAR' => ['EUR' => null, 'SAR' => null, 'USD' => null]]);
    });

    it('reports an unknown central bank as a service error', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('nope')
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(allratestodayClient($this->requests, json_encode(['error' => 'Unknown central bank: nope'])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rates can\'t be retrieved.']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('does not call the service for a EUR-only store', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR'], ['EUR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayEcbTable([])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe([]);
        expect($rates)->toBe(['EUR' => ['EUR' => 1]]);
    });
});
