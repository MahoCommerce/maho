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

        $validator = new Maho_FeedManager_Model_Validator();
        expect($validator->validate($path, 'csv'))->toBeTrue()
            ->and($validator->getWarnings())->toBe([]);

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
    'quote after backslash followed by comma' => ['A \\"quoted" product, with comma'],
    'backslash at the end' => ['C:\\catalog\\'],
    'comma and newline' => ["A product, with\na second line"],
    'quotes' => ['A "quoted" product'],
]);

it('validates a feed with the delimiter and the enclosure of the feed', function (string $delimiter, string $enclosure): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-csv-');
    $writer = new Maho_FeedManager_Model_Writer_Csv()->setDelimiter($delimiter)->setEnclosure($enclosure);

    try {
        $writer->open($path);
        $writer->writeProduct(['title' => 'Shoes,red', 'sku' => 'next-column']);
        $writer->writeProduct(['title' => "It's red", 'sku' => 'next-column']);
        $writer->close();

        $validator = new Maho_FeedManager_Model_Validator();
        expect($validator->validate($path, 'csv', $delimiter, $enclosure))->toBeTrue()
            ->and($validator->getWarnings())->toBe([]);
    } finally {
        $writer->close();
        unlink($path);
    }
})->with([
    'pipe' => ['|', '"'],
    'semicolon' => [';', '"'],
    'single quote' => [',', "'"],
]);

it('writes a feed with no enclosure', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-csv-');
    $writer = new Maho_FeedManager_Model_Writer_Csv()->setDelimiter('|')->setEnclosure('');

    try {
        $writer->open($path);
        $writer->writeProduct(['title' => 'A "quoted" product, red', 'sku' => 'next-column']);
        $writer->close();

        expect(file_get_contents($path))->toBe("title|sku\nA \"quoted\" product, red|next-column\n");

        $validator = new Maho_FeedManager_Model_Validator();
        expect($validator->validate($path, 'csv', '|', ''))->toBeTrue()
            ->and($validator->getWarnings())->toBe([]);
    } finally {
        $writer->close();
        unlink($path);
    }
});
