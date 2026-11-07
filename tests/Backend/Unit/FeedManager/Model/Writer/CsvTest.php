<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

it('writes quotes after backslashes so CSV readers recover every field', function (string $value): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-csv-');
    $writer = new Maho_FeedManager_Model_Writer_Csv();

    try {
        $writer->open($path);
        $writer->writeProduct(['title' => $value, 'sku' => 'next-column']);
        $writer->close();

        $stream = fopen($path, 'r');
        try {
            expect(fgetcsv($stream, escape: ''))->toBe(['title', 'sku'])
                ->and(fgetcsv($stream, escape: ''))->toBe([$value, 'next-column'])
                ->and(fgetcsv($stream, escape: ''))->toBeFalse();
        } finally {
            fclose($stream);
        }
    } finally {
        $writer->close();
        unlink($path);
    }
})->with([
    'quote after backslash' => ['A \\"quoted" product'],
    'backslash at the end' => ['C:\\catalog\\'],
    'comma and newline' => ["A product, with\na second line"],
    'quotes' => ['A "quoted" product'],
]);
