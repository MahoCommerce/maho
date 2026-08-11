<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Tests\Helpers\ExchangerateapiHarness;
use Tests\Helpers\FixerioHarness;
use Tests\Helpers\FrankfurterHarness;
use Tests\TestEnv;

uses(Tests\MahoBackendTestCase::class)->group('currency-live');

/**
 * Talks to the real rate services, so it catches them changing under us (endpoint, auth,
 * response shape). The offline coverage lives in
 * tests/Backend/Unit/Directory/Model/Currency/Import/.
 */
function expectUsableRates(Mage_Directory_Model_Currency_Import_Eurbased $importer): void
{
    $rates = $importer->fetchRates();

    expect($importer->getMessages())->toBe([]);

    foreach (['EUR', 'USD'] as $base) {
        expect((float) $rates[$base][$base])->toBe(1.0);
        foreach (['EUR', 'GBP', 'USD'] as $code) {
            expect($rates[$base][$code])->toBeNumeric();
            // Loose bounds: a major-currency pair this far out is bad data, not a market move.
            expect((float) $rates[$base][$code])->toBeGreaterThan(0.01)->toBeLessThan(100);
        }
    }

    expect((float) $rates['EUR']['USD'] * (float) $rates['USD']['EUR'])->toEqualWithDelta(1.0, 0.000001);
}

it('imports usable rates from Frankfurter', function () {
    expectUsableRates((new FrankfurterHarness())->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD']));
});

it('imports usable rates from ExchangeRate-API', function () {
    expectUsableRates((new ExchangerateapiHarness())->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD']));
});

describe('Fixer', function () {
    beforeEach(function () {
        if (!TestEnv::has('FIXERIO_API_KEY')) {
            test()->markTestSkipped('Fixer API key not set (FIXERIO_API_KEY)');
        }
        FixerioHarness::storeApiKey(TestEnv::get('FIXERIO_API_KEY'));
    });

    it('gets EUR-based rates from the live service', function () {
        $url = str_replace(
            ['{{ACCESS_KEY}}', '{{SYMBOLS}}'],
            [TestEnv::get('FIXERIO_API_KEY'), 'EUR,GBP,USD'],
            (new FixerioHarness())->getUrlTemplate(),
        );

        $response = HttpClient::create(['timeout' => 30])->request('GET', $url);
        $body = json_decode($response->getContent(false), true);

        expect($response->getStatusCode())->toBe(200);
        expect($body['error']['type'] ?? null)->toBeNull();
        expect($body['success'] ?? null)->toBeTrue();
        expect($body['base'] ?? null)->toBe('EUR');
    });

    it('imports usable rates through the currency importer', function () {
        expectUsableRates((new FixerioHarness())->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD']));
    });
});
