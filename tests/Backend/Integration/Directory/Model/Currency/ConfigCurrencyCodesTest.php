<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A configured list can arrive as "USD, xtb", while the rate table answers on trimmed, uppercased
 * codes; a raw spelling would give the admin rate matrix a row and column that match nothing.
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

    // The list is merged across scopes, which is how two spellings of one code end up in it
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
