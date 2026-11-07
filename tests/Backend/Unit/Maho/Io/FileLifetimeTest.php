<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Io\File lifetime', function () {
    it('frees an instance that nothing references', function () {
        $io = new \Maho\Io\File();
        $reference = WeakReference::create($io);
        unset($io);

        expect($reference->get())->toBeNull();
    });

    it('closes the stream when the instance is freed', function () {
        $dir = sys_get_temp_dir() . '/maho_io_lifetime_' . uniqid();
        mkdir($dir, 0755, true);
        $io = new \Maho\Io\File();
        $io->open(['path' => $dir]);
        $io->streamOpen('closed.txt', 'w+', 0700);
        $io->streamWrite('data');
        unset($io);
        clearstatcache();

        expect(fileperms($dir . '/closed.txt') & 0777)->toBe(0700)
            ->and(file_get_contents($dir . '/closed.txt'))->toBe('data');

        unlink($dir . '/closed.txt');
        rmdir($dir);
    });
});
