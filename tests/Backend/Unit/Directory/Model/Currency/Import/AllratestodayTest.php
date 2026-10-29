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
        $requests[] = $url;
        $GLOBALS['allratestodayHeaders'][] = json_encode($options['headers'] ?? []);
        return new MockResponse($body);
    });
}

function allratestodayEcbTable(array $rates): string
{
    return json_encode(['result' => 'success', 'base_code' => 'EUR', 'date' => '2026-10-28', 'rates' => $rates]);
}

/**
 * @param array<array{0: string, 1: string, 2: mixed, 3?: string}> $rows [base, quote, value, type] = "1 base = value quote"
 */
function allratestodayBankTable(string $bank, array $rows, bool $stale = false): string
{
    $rates = [];
    foreach ($rows as $row) {
        $rates[] = ['base' => $row[0], 'quote' => $row[1], 'type' => $row[3] ?? 'reference', 'value' => $row[2]];
    }

    return json_encode(['bank' => $bank, 'rate_date' => '2026-10-28', 'age_days' => 1, 'stale' => $stale, 'rates' => $rates]);
}

describe('AllRatesToday currency import', function () {
    beforeEach(function () {
        $this->requests = [];
        $GLOBALS['allratestodayHeaders'] = [];
    });

    it('fetches the EUR-based ECB table in a single request', function () {
        $importer = (new AllratestodayHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayEcbTable(['EUR' => 1, 'GBP' => 0.85, 'USD' => 1.10, 'JPY' => 160.0])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe(['https://allratestoday.com/api/open/latest/EUR']);
        expect($GLOBALS['allratestodayHeaders'][0])->not->toContain('Authorization');
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
            ->setApiKey('test-key')
            ->setCurrencies(['EUR', 'SAR', 'USD'], ['SAR', 'EUR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('sama', [
                ['USD', 'SAR', 3.75],
                ['EUR', 'SAR', 4.5],
                ['GBP', 'SAR', 5.0],
            ])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe(['https://allratestoday.com/api/v1/central-bank/sama/latest']);
        expect($GLOBALS['allratestodayHeaders'][0])->toContain('Bearer test-key');
        expect($importer->getMessages())->toBe([]);
        expect($rates['SAR']['USD'])->toEqualWithDelta(1 / 3.75, 0.000001);
        expect($rates['SAR']['EUR'])->toEqualWithDelta(1 / 4.5, 0.000001);
        expect($rates['EUR']['SAR'])->toEqualWithDelta(4.5, 0.000001);
        expect($rates['EUR']['USD'])->toEqualWithDelta(4.5 / 3.75, 0.000001);
    });

    it('re-bases a central bank table quoted per home unit on EUR', function () {
        // RBNZ quotes foreign currency per 1 NZD: 1 NZD = 0.5 USD, 1 NZD = 0.5 EUR
        $importer = (new AllratestodayHarness())
            ->setBank(' RBNZ ')
            ->setApiKey('test-key')
            ->setCurrencies(['EUR', 'NZD', 'USD'], ['NZD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('rbnz', [
                ['NZD', 'USD', 0.5],
                ['NZD', 'EUR', 0.5],
                ['NZD', 'AUD', 0.8],
            ])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe(['https://allratestoday.com/api/v1/central-bank/rbnz/latest']);
        expect($importer->getMessages())->toBe([]);
        expect($rates['NZD']['USD'])->toEqualWithDelta(0.5, 0.000001);
        expect($rates['NZD']['EUR'])->toEqualWithDelta(0.5, 0.000001);
        expect($rates['NZD']['NZD'])->toEqual(1);
    });

    it('takes the middle rate when a central bank publishes buy and sell rows', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('bb')
            ->setApiKey('test-key')
            ->setCurrencies(['BDT', 'EUR', 'USD'], ['BDT'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('bb', [
                ['EUR', 'BDT', 137.0, 'buy'],
                ['EUR', 'BDT', 139.0, 'sell'],
                ['USD', 'BDT', 119.0, 'buy'],
                ['USD', 'BDT', 120.0, 'reference'],
                ['USD', 'BDT', 121.0, 'sell'],
            ])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect($rates['BDT']['EUR'])->toEqualWithDelta(1 / 138.0, 0.000001);
        expect($rates['BDT']['USD'])->toEqualWithDelta(1 / 120.0, 0.000001);
    });

    it('skips central bank rows with a value of zero or not a number', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('rbnz')
            ->setApiKey('test-key')
            ->setCurrencies(['EUR', 'NZD', 'USD'], ['NZD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('rbnz', [
                ['NZD', 'USD', 0.5],
                ['NZD', 'EUR', 0.5],
                ['NZD', 'JPY', 0],
                ['NZD', 'AUD', 'n/a'],
            ])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect($rates['NZD']['USD'])->toEqualWithDelta(0.5, 0.000001);
    });

    it('reports a central bank table without a EUR rate', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('sama')
            ->setApiKey('test-key')
            ->setCurrencies(['EUR', 'SAR', 'USD'], ['SAR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('sama', [['USD', 'SAR', 3.75]])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Central bank sama publishes no EUR rate.']);
        expect($rates)->toBe(['SAR' => ['EUR' => null, 'SAR' => null, 'USD' => null]]);
    });

    it('reports an API error envelope as a service error', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('nope')
            ->setApiKey('test-key')
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(allratestodayClient($this->requests, json_encode(['error' => 'Unknown central bank: nope'])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Currency rates can\'t be retrieved.']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('requires an API key for a central bank table', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('sama')
            ->setCurrencies(['EUR', 'SAR'], ['SAR'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('sama', [['EUR', 'SAR', 4.5]])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe([]);
        expect($importer->getMessages())->toBe(['An API key is required to import a central bank table from AllRatesToday.']);
        expect($rates)->toBe(['SAR' => ['EUR' => null, 'SAR' => null]]);
    });

    it('rejects a stale central bank table so the import can fall back to another service', function () {
        $importer = (new AllratestodayHarness())
            ->setBank('cbvs')
            ->setApiKey('test-key')
            ->setCurrencies(['EUR', 'SRD'], ['SRD'])
            ->setHttpClient(allratestodayClient($this->requests, allratestodayBankTable('cbvs', [['EUR', 'SRD', 40.0]], true)));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(['Central bank cbvs has not published a fresh table; the last one is dated 2026-10-28.']);
        expect($rates)->toBe(['SRD' => ['EUR' => null, 'SRD' => null]]);
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
