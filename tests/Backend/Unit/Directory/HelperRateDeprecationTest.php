<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * The helper is what the currency model's rate methods were deprecated in favour of, so the
 * helper answering through them would raise E_USER_DEPRECATED on every price render from
 * PHP 8.4 on. It answers from the resource instead, and this pins that.
 */

it('answers a rate without running a deprecated delegate', function () {
    if (PHP_VERSION_ID < 80400) {
        $this->markTestSkipped('#[\Deprecated] raises E_USER_DEPRECATED from PHP 8.4 on');
    }

    $deprecations = [];
    set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
        $deprecations[] = $errstr;
        return true;
    }, E_USER_DEPRECATED);

    try {
        Mage::helper('directory')->getRate('USD', 'USD');
        Mage::helper('directory')->getAnyRate('USD', 'USD');
        Mage::helper('directory')->convert(10.0, 'USD', 'USD');
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([]);
});
