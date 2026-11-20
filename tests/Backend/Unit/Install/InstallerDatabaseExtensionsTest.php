<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('allows any supported PDO driver in the installer environment check', function (): void {
    $extensions = Mage::getSingleton('install/config')->getExtensionsForCheck();
    $drivers = ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'];

    expect($extensions)->toContain($drivers)
        ->and(array_intersect($drivers, array_keys($extensions)))->toBe([]);
});
