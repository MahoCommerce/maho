<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

it('reports an unencodable product instead of silently omitting it', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-jsonl-');
    $writer = new Maho_FeedManager_Model_Writer_Jsonl();

    try {
        $writer->open($path);
        expect(fn() => $writer->writeProduct(['id' => 'bad-product', 'price' => INF]))
            ->toThrow(JsonException::class);

        $writer->writeProduct(['id' => 'next-product', 'price' => 12.50]);
        $writer->close();

        expect(file_get_contents($path))->toBe('{"id":"next-product","price":12.5}' . "\n");
    } finally {
        $writer->close();
        unlink($path);
    }
});
