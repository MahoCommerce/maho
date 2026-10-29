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
    return new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, $body): MockResponse {
        $requests[] = ['url' => $url, 'headers' => json_encode($options['headers'] ?? [])];
        return new MockResponse($body);
    });
}

function allratestodayEcbTable(array $rates): string
{
    return json_encode(['result' => 'success', 'base_code' => 'EUR', 'date' => '2026-10-28', 'rates' => $rates]);
}

/**
 * @param array<array{string, string, float, 3?: string}> $rows [base, quote, value, type] = "1 base = value quote"
 */
function allratestodayBankTable(string $bank, array $rows, bool $stale = false): string
{
    $rates = [];
    foreach ($rows as $row) {
        $rates[] = ['base' => $row[0], 'quote' => $row[1], 'type' => $row[3] ?? 'reference', 'value' => $row[2]];
    }

    return json_encode(['bank' => $bank, 'rate_date' => '2026-10-28', 'age_days' => 1, 'stale' => $stale, 'rates' => $rates]);
}

function allratestodayBank(array &$requests, string $body, string $bank = 'sama'): AllratestodayHarness
{
    return (new AllratestodayHarness())
        ->setBank($bank)
        ->setApiKey('test-key')
        ->setHttpClient(allratestodayClient($requests, $body));
}

describe('AllRatesToday currency import', function () {
    beforeEach(function () {
        $this->requests = [];
    });

    it('fetches the EUR-based ECB table in a single keyless request', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayEcbTable(['EUR' => 1, 'GBP' => 0.85, 'USD' => 1.10, 'JPY' => 160.0])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toHaveCount(1);
        expect($this->requests[0]['url'])->toBe('https://allratestoday.com/api/open/latest/EUR');
        expect($this->requests[0]['headers'])->not->toContain('Authorization');
        expect($importer->getMessages())->toBe([]);
        expect($rates['EUR']['USD'])->toEqualWithDelta(1.10, 0.000001);
        expect($rates['EUR']['EUR'])->toEqual(1);
        expect($rates['USD']['GBP'])->toEqualWithDelta(0.85 / 1.10, 0.000001);
        expect($rates['USD'])->not->toHaveKey('JPY');
    });

    it('passes an ECB error envelope through as a service error', function () {
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

    it('does not call the service for a EUR-only store', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR'], ['EUR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayEcbTable([])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe([]);
        expect($rates)->toBe(['EUR' => ['EUR' => 1]]);
    });

    it('requires an API key for a central bank table', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('sama')
            ->setCurrencies(['EUR', 'SAR'], ['SAR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('sama', [['EUR', 'SAR', 4.5]])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe([]);
        expect($importer->getMessages())->toBe(['An API key is required to import central bank tables from AllRatesToday.']);
        expect($rates)->toBe(['SAR' => ['EUR' => null, 'SAR' => null]]);
    });

    it('fetches a central bank table with the API key and re-bases it on EUR', function () {
        // SAMA quotes SAR per unit of foreign currency: 1 USD = 3.75 SAR, 1 EUR = 4.5 SAR
        $importer = allratestodayBank($this->requests, allratestodayBankTable('sama', [
            ['USD', 'SAR', 3.75],
            ['EUR', 'SAR', 4.5],
            ['GBP', 'SAR', 5.0],
        ]))->setCurrencies(['EUR', 'SAR', 'USD'], ['SAR', 'EUR']);

        $rates = $importer->fetchRates();

        expect($this->requests)->toHaveCount(1);
        expect($this->requests[0]['url'])->toBe('https://allratestoday.com/api/v1/central-bank/sama/latest');
        expect($this->requests[0]['headers'])->toContain('Bearer test-key');
        expect($importer->getMessages())->toBe([]);
        expect($rates['SAR']['USD'])->toEqualWithDelta(1 / 3.75, 0.000001);
        expect($rates['SAR']['EUR'])->toEqualWithDelta(1 / 4.5, 0.000001);
        expect($rates['EUR']['SAR'])->toEqualWithDelta(4.5, 0.000001);
        expect($rates['EUR']['USD'])->toEqualWithDelta(4.5 / 3.75, 0.000001);
    });

    it('re-bases a central bank table quoted per home unit on EUR', function () {
        // RBNZ quotes foreign currency per 1 NZD: 1 NZD = 0.5 USD, 1 NZD = 0.5 EUR
        $importer = allratestodayBank($this->requests, allratestodayBankTable('rbnz', [
            ['NZD', 'USD', 0.5],
            ['NZD', 'EUR', 0.5],
            ['NZD', 'AUD', 0.8],
        ]), 'RBNZ')->setCurrencies(['EUR', 'NZD', 'USD'], ['NZD']);

        $rates = $importer->fetchRates();

        expect($this->requests[0]['url'])->toBe('https://allratestoday.com/api/v1/central-bank/rbnz/latest');
        expect($importer->getMessages())->toBe([]);
        expect($rates['NZD']['USD'])->toEqualWithDelta(0.5, 0.000001);
        expect($rates['NZD']['EUR'])->toEqualWithDelta(0.5, 0.000001);
        expect($rates['NZD']['NZD'])->toEqual(1);
    });

    it('uses the reference rows when a table also carries buy and sell rows', function () {
        $importer = allratestodayBank($this->requests, allratestodayBankTable('bccr', [
            ['USD', 'CRC', 500.0, 'buy'],
            ['USD', 'CRC', 510.0, 'sell'],
            ['USD', 'CRC', 505.0, 'reference'],
            ['EUR', 'CRC', 550.0, 'buy'],
            ['EUR', 'CRC', 560.0, 'sell'],
            ['EUR', 'CRC', 555.0, 'reference'],
        ]), 'bccr')->setCurrencies(['EUR', 'CRC', 'USD'], ['CRC']);

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect($rates['CRC']['USD'])->toEqualWithDelta(1 / 505.0, 0.00000001);
        expect($rates['CRC']['EUR'])->toEqualWithDelta(1 / 555.0, 0.00000001);
    });

    it('imports a buy/sell-only table at the midpoint', function () {
        $importer = allratestodayBank($this->requests, allratestodayBankTable('tcmb', [
            ['USD', 'TRY', 40.0, 'buy'],
            ['USD', 'TRY', 42.0, 'sell'],
            ['EUR', 'TRY', 46.0, 'buy'],
            ['EUR', 'TRY', 48.0, 'sell'],
        ]), 'tcmb')->setCurrencies(['EUR', 'TRY', 'USD'], ['TRY']);

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect($rates['TRY']['USD'])->toEqualWithDelta(1 / 41.0, 0.00000001);
        expect($rates['TRY']['EUR'])->toEqualWithDelta(1 / 47.0, 0.00000001);
    });

    it('rejects a stale table so the import can fall back to another service', function () {
        $importer = allratestodayBank($this->requests, allratestodayBankTable('cbvs', [['EUR', 'SRD', 40.0]], true), 'cbvs')
            ->setCurrencies(['EUR', 'SRD'], ['SRD']);

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rate service error: cbvs: the table dated 2026-10-28 is stale']);
        expect($rates)->toBe(['SRD' => ['EUR' => null, 'SRD' => null]]);
    });

    it('reports a central bank table without a EUR rate', function () {
        $importer = allratestodayBank($this->requests, allratestodayBankTable('sama', [['USD', 'SAR', 3.75]]))
            ->setCurrencies(['EUR', 'SAR', 'USD'], ['SAR']);

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rate service error: sama: the table has no EUR rate']);
        expect($rates)->toBe(['SAR' => ['EUR' => null, 'SAR' => null, 'USD' => null]]);
    });

    it('reports a table without a usable rate type', function () {
        $importer = allratestodayBank($this->requests, allratestodayBankTable('ustreasury', [['EUR', 'USD', 1.1, 'quarterly']]), 'ustreasury')
            ->setCurrencies(['EUR', 'USD'], ['USD']);

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rate service error: ustreasury: no usable rate type in the table (quarterly)']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('passes an API error envelope through as a service error', function () {
        $importer = allratestodayBank($this->requests, json_encode(['error' => 'Unknown central bank: nope']), 'nope')
            ->setCurrencies(['EUR', 'USD'], ['USD']);

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rate service error: nope: Unknown central bank: nope']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });
});
