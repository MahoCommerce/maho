<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('gives the group class of an alias with no class part, with no PHP notice', function (string $suffix): void {
    // A group name that no other call cached, so the method writes its cache entry here
    $group = 'groupedclassnametest' . bin2hex(random_bytes(4));

    set_error_handler(static fn(int $errno, string $errstr): never => throw new ErrorException($errstr, 0, $errno));
    try {
        expect(Mage::getConfig()->getGroupedClassName('block', $group . $suffix))
            ->toBe(uc_words('mage_' . $group . '_block'));
    } finally {
        restore_error_handler();
    }
})->with(['trailing slash' => ['/'], 'empty class part' => ['//blog']]);
