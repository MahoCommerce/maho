<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator;
use League\Flysystem\Visibility;
use Maho\Storage\Mount;

function storageTestRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

describe('Maho\Storage\Mount on a local directory', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_storage_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);
    });

    afterEach(function (): void {
        storageTestRemoveDir($this->root);
    });

    it('knows its name and its local root', function (): void {
        expect($this->mount->name())->toBe('media')
            ->and($this->mount->isLocal())->toBeTrue()
            ->and($this->mount->localRoot())->toBe($this->root);
    });

    it('moves a string into place through a temp file and leaves none behind', function (): void {
        $this->mount->moveAtomic('catalog/a.txt', 'hello');

        expect($this->mount->read('catalog/a.txt'))->toBe('hello')
            ->and(glob($this->root . '/catalog/*.tmp'))->toBe([]);
    });

    it('moves a stream into place through a temp file', function (): void {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'streamed');
        rewind($stream);

        $this->mount->moveAtomic('catalog/b.txt', $stream);
        fclose($stream);

        expect($this->mount->read('catalog/b.txt'))->toBe('streamed')
            ->and(glob($this->root . '/catalog/*.tmp'))->toBe([]);
    });

    it('replaces an existing file with moveAtomic', function (): void {
        $this->mount->write('a.txt', 'old');
        $this->mount->moveAtomic('a.txt', 'new');

        expect($this->mount->read('a.txt'))->toBe('new')
            ->and(array_map(basename(...), glob($this->root . '/*')))->toBe(['a.txt']);
    });

    it('applies the default visibility to a write', function (): void {
        $mount = new Mount('media', new LocalFilesystemAdapter($this->root, PortableVisibilityConverter::fromArray([
            'file' => ['public' => 0666, 'private' => 0600],
        ])), $this->root, null, ['visibility' => Visibility::PRIVATE]);

        $mount->write('secret.txt', 's');

        expect(fileperms($this->root . '/secret.txt') & 0777)->toBe(0600);
    });

    it('lists what it wrote', function (): void {
        $this->mount->write('x/1.txt', '1');
        $this->mount->write('x/y/2.txt', '2');

        $paths = array_map(fn($item) => $item->path(), $this->mount->listContents('x', true)->toArray());
        sort($paths);

        expect($paths)->toBe(['x/1.txt', 'x/y', 'x/y/2.txt']);
    });

    it('has no public url without a generator', function (): void {
        expect(fn() => $this->mount->publicUrl('a.txt'))->toThrow(UnableToGeneratePublicUrl::class);
    });

    it('builds a public url from an explicit prefix', function (): void {
        $mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new PrefixPublicUrlGenerator('https://cdn.example.com/media/'));

        expect($mount->publicUrl('catalog/a.jpg'))->toBe('https://cdn.example.com/media/catalog/a.jpg');
    });

    it('has no temporary urls on a local disk', function (): void {
        expect($this->mount->supportsTemporaryUrls())->toBeFalse()
            ->and(fn() => $this->mount->temporaryUrl('a.txt', new DateTimeImmutable('+1 hour')))
            ->toThrow(UnableToGenerateTemporaryUrl::class);
    });
});
