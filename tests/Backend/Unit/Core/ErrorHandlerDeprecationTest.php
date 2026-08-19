<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * PHP 8.4's native #[\Deprecated] attribute raises E_USER_DEPRECATED, which the error handler
 * used to label "Unknown error (16384)".
 */

it('labels a native deprecation instead of calling it an unknown error', function () {
    $wasDeveloperMode = Mage::getIsDeveloperMode();
    $errorLevel = error_reporting(E_ALL);
    Mage::setIsDeveloperMode(true);

    try {
        expect(fn() => mageCoreErrorHandler(E_USER_DEPRECATED, 'Method X::y() is deprecated', __FILE__, __LINE__))
            ->toThrow(Exception::class, 'User Deprecated Functionality');
    } finally {
        Mage::setIsDeveloperMode($wasDeveloperMode);
        error_reporting($errorLevel);
    }
});
