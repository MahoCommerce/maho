<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

/**
 * Mage_Core_Model_Translate::getModulesConfig() reads <translate><modules> from the node of the
 * current area only: frontend or adminhtml. A module CSV that is declared nowhere else is never loaded.
 */

uses(MahoBackendTestCase::class);

/**
 * @return list<string> the module names that have a CSV in app/locale/en_US
 */
function mahoModulesWithCsv(): array
{
    $modules = [];
    foreach (glob(Mage::getBaseDir() . '/app/locale/en_US/Maho_*.csv') as $path) {
        $modules[] = basename($path, '.csv');
    }
    return $modules;
}

it('declares the CSV of every Maho module in the frontend or adminhtml area', function () {
    $missing = array_filter(
        mahoModulesWithCsv(),
        fn(string $module): bool => Mage::getConfig()->getNode("frontend/translate/modules/$module") === false
            && Mage::getConfig()->getNode("adminhtml/translate/modules/$module") === false,
    );

    expect(array_values($missing))->toBe([]);
});

it('declares no translate file in the global area', function () {
    $global = Mage::getConfig()->getNode('global/translate/modules');

    expect($global === false ? [] : array_keys($global->asArray()))->toBe([]);
});

it('loads the Gift Card CSV on the storefront', function () {
    $node = Mage::getConfig()->getNode('frontend/translate/modules/Maho_Giftcard/files/default');

    expect((string) $node)->toBe('Maho_Giftcard.csv');
});
