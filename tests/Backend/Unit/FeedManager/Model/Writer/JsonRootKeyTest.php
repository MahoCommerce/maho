<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('preserves the root name and product list in generated JSON', function (?string $rootKey, string $expectedKey, bool $prettyPrint, array $products): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-json-root-');
    $writer = new Maho_FeedManager_Model_Writer_Json();
    $writer->setPrettyPrint($prettyPrint);
    if ($rootKey !== null) {
        $writer->setRootKey($rootKey);
    }

    try {
        $writer->open($path);
        foreach ($products as $product) {
            $writer->writeProduct($product);
        }
        $writer->close();

        expect(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR))
            ->toBe([$expectedKey => $products]);
        expect(new Maho_FeedManager_Model_Validator()->validate($path, 'json'))->toBeTrue();
    } finally {
        $writer->close();
        unlink($path);
    }
})->with([
    'default' => [null, 'products'],
    'empty falls back' => ['', 'products'],
    'zero keeps existing fallback' => ['0', 'products'],
    'custom' => ['items', 'items'],
    'quote' => ['catalog"items', 'catalog"items'],
    'backslash' => ['catalog\\stock', 'catalog\\stock'],
    'escape-like text' => ['catalog\\new', 'catalog\\new'],
    'unicode' => ['Produits été 🛍', 'Produits été 🛍'],
])->with([false, true])->with([
    'empty list' => [[]],
    'two products' => [[['id' => 'first', 'title' => 'Été'], ['id' => 'second', 'price' => 12.5]]],
]);

it('uses the configured root name for a generated feed larger than the full-validation threshold', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-json-root-');
    $rootKey = 'catalog"entries\\stock';
    $feed = new Maho_FeedManager_Model_Feed();
    $feed->setJsonRootKey($rootKey);
    $writer = new Maho_FeedManager_Model_Writer_Json();
    $writer->configureFromFeed($feed);
    $products = [['id' => 'large', 'description' => str_repeat('x', 2 * 1024 * 1024)]];

    try {
        $writer->open($path);
        $writer->writeProduct($products[0]);
        $writer->close();

        expect(filesize($path))->toBeGreaterThan(2 * 1024 * 1024);
        expect(new Maho_FeedManager_Model_Validator()->validate($path, 'json'))->toBeTrue();
        expect(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR))
            ->toBe([$rootKey => $products]);
    } finally {
        $writer->close();
        unlink($path);
    }
});

it('rejects an unencodable root before replacing an existing artifact', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-json-root-');
    $original = '{"products":[{"id":"previous"}]}';
    file_put_contents($path, $original);
    $writer = new Maho_FeedManager_Model_Writer_Json();
    $writer->setRootKey("invalid\xFF");

    try {
        expect(fn() => $writer->open($path))->toThrow(JsonException::class);
        $writer->close();

        expect(file_get_contents($path))->toBe($original);
    } finally {
        $writer->close();
        unlink($path);
    }
});
