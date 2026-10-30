<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('lets a warning reach the active error handler', function () {
    $seen = [];
    set_error_handler(function (int $errno) use (&$seen): bool {
        $seen[] = $errno;
        return true;
    });

    try {
        withoutDeprecations(fn() => trigger_error('x', E_USER_WARNING));
    } finally {
        restore_error_handler();
    }

    expect($seen)->toBe([E_USER_WARNING]);
});

it('restores the error level after the call', function () {
    $level = error_reporting();

    withoutDeprecations(fn() => null);

    expect(error_reporting())->toBe($level);
});
