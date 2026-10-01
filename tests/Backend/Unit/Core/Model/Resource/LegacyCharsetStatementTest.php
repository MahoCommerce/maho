<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

it('recognizes the SET NAMES utf8 that older installers wrote to local.xml', function (string $statement) {
    expect(Mage_Core_Model_Resource::isLegacyCharsetStatement($statement))->toBeTrue();
})->with([
    'plain' => ['SET NAMES utf8'],
    'utf8mb3' => ['SET NAMES utf8mb3'],
    'quoted, with semicolon' => [" set names 'utf8';\n"],
]);

it('keeps any other init statement', function (string $statement) {
    expect(Mage_Core_Model_Resource::isLegacyCharsetStatement($statement))->toBeFalse();
})->with([
    'utf8mb4' => ['SET NAMES utf8mb4'],
    'with a collation' => ['SET NAMES utf8 COLLATE utf8_unicode_ci'],
    'another statement' => ['SET SESSION wait_timeout = 600'],
]);
