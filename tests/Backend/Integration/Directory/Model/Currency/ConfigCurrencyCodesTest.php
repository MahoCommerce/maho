<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Configuration is where currency codes enter the system, and a configured list is a string a CLI,
 * an import or a config.xml can write as "USD, xtb". The rate table answers on trimmed, uppercased
 * codes, so a list that keeps the raw spelling produces two codes for one currency: the admin rate
 * matrix then renders a row whose rates it cannot find, and a column beside the real one that
 * matches nothing.
 *
 * The code is an ISO 4217 "X" code no real currency uses.
 */
const CONFIG_CODES_CURRENCY = 'XTB';

function configCodesNode(string $path): ?string
{
    $node = Mage::getConfig()->getNode($path);

    return $node === false ? null : (string) $node;
}

it('lists configured codes in the spelling the rate table answers on', function () {
    $store = Mage::app()->getStore(1);
    $storeCode = (string) $store->getCode();
    $basePath = "stores/{$storeCode}/currency/options/base";
    $allowPath = "stores/{$storeCode}/currency/options/allow";

    $defaultAllowPath = 'default/currency/options/allow';

    $originalBase = configCodesNode($basePath) ?? (string) $store->getBaseCurrencyCode();
    $originalAllow = configCodesNode($allowPath) ?? implode(',', $store->getAvailableCurrencyCodes());
    $originalDefaultAllow = configCodesNode($defaultAllowPath) ?? 'USD';

    // The list is merged across scopes, so one currency spelled one way here and another way there
    // is how two spellings end up in it.
    Mage::getConfig()->setNode($defaultAllowPath, 'USD,' . CONFIG_CODES_CURRENCY);
    Mage::getConfig()->setNode($basePath, ' xtb ');
    Mage::getConfig()->setNode($allowPath, 'USD, xtb');

    try {
        /** @var Mage_Directory_Model_Currency $currency */
        $currency = Mage::getModel('directory/currency');
        $defaults = $currency->getConfigBaseCurrencies();
        $allowed = $currency->getConfigAllowCurrencies();

        expect($defaults)->toContain(CONFIG_CODES_CURRENCY);

        // Two spellings of one code are two columns in the matrix, one of them empty.
        $spellings = array_map(fn(string $code): string => strtoupper(trim($code)), $allowed);
        expect(count(array_unique($spellings)))->toBe(count(array_unique($allowed)));

        // Every row the matrix renders looks its rates up under the code it printed.
        $rates = $currency->getCurrencyRates($defaults, $allowed);
        foreach ($defaults as $code) {
            expect($rates)->toHaveKey($code);
        }
    } finally {
        Mage::getConfig()->setNode($basePath, $originalBase);
        Mage::getConfig()->setNode($allowPath, $originalAllow);
        Mage::getConfig()->setNode($defaultAllowPath, $originalDefaultAllow);
    }
});
