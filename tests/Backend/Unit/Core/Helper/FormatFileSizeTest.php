<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('renders a byte count in the largest readable binary unit', function (int $bytes, string $expected) {
    expect(Mage::helper('core')->formatFileSize($bytes))->toBe($expected);
})->with([
    [0, '0 B'],
    [500, '500 B'],
    [1023, '1023 B'],
    [1024, '1 KB'],
    [1536, '1.5 KB'],
    [1048576, '1 MB'],
    [1073741824, '1 GB'],
    [1099511627776, '1 TB'],
]);

it('never renders a fractional byte', function () {
    expect(Mage::helper('core')->formatFileSize(1))->toBe('1 B');
});

it('treats a negative size as empty rather than rendering a negative unit', function () {
    expect(Mage::helper('core')->formatFileSize(-1))->toBe('0 B');
});

it('keeps using the largest unit beyond its range instead of overflowing the unit list', function () {
    expect(Mage::helper('core')->formatFileSize(5 * 1024 ** 5))->toBe('5120 TB');
});
