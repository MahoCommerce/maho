<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\ExchangerateapiHarness;
use Tests\Helpers\FrankfurterHarness;

uses(Tests\MahoBackendTestCase::class)->group('currency-live');

/**
 * Talks to the real rate services, so it catches them changing under us (endpoint, auth,
 * response shape). The offline coverage lives in
 * tests/Backend/Unit/Directory/Model/Currency/Import/.
 *
 * Only the keyless services are covered here. Fixer's free tier allows far fewer calls per
 * month than CI makes, so a live check of it cannot run often enough to be worth having.
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
