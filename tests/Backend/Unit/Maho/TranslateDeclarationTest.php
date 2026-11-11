<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

/**
 * Mage_Core_Model_Translate::getModulesConfig() reads <translate><modules> from the node of the
 * current area only: frontend or adminhtml. The translator ignores a CSV that only the global node declares.
 */

uses(MahoBackendTestCase::class);

function mahoDeclaresCsv(string $area, string $module): bool
{
    return (string) Mage::getConfig()->getNode("$area/translate/modules/$module/files/default") === "$module.csv";
}

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
        fn(string $module): bool => !mahoDeclaresCsv('frontend', $module) && !mahoDeclaresCsv('adminhtml', $module),
    );

    expect(array_values($missing))->toBe([]);
});

it('declares the CSV of every Maho module that has admin configuration in the adminhtml area', function () {
    $missing = array_filter(
        mahoModulesWithCsv(),
        fn(string $module): bool => is_file(Mage::getBaseDir() . '/app/code/core/Maho/' . substr($module, 5) . '/etc/system.xml')
            && !mahoDeclaresCsv('adminhtml', $module),
    );

    expect(array_values($missing))->toBe([]);
});

it('loads the ContentNegotiation CSV on the storefront', function () {
    expect(mahoDeclaresCsv('frontend', 'Maho_ContentNegotiation'))->toBeTrue();
});

it('declares no translate file in the global area', function () {
    $global = Mage::getConfig()->getNode('global/translate/modules');

    expect($global === false ? [] : array_keys($global->asArray()))->toBe([]);
});

it('loads the Gift Card CSV on the storefront', function () {
    expect(mahoDeclaresCsv('frontend', 'Maho_Giftcard'))->toBeTrue();
});
