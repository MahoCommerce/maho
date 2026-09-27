<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use League\Flysystem\UnableToMoveFile;
use Maho\Storage\AdapterFactory;
use Maho\Storage\Mount;
use Maho\Storage\MountDefinition;
use Tests\TestEnv;

describe('Maho\Storage\Mount on Azure Blob Storage', function () {
    beforeEach(function (): void {
        if (!class_exists(\AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter::class)) {
            $this->markTestSkipped('azure-oss/storage-blob-flysystem is not installed');
        }
        if (!TestEnv::has('MAHO_TEST_AZURE_CONNECTION_STRING')) {
            $this->markTestSkipped('MAHO_TEST_AZURE_CONNECTION_STRING is not set');
        }
        $connectionString = TestEnv::get('MAHO_TEST_AZURE_CONNECTION_STRING');
        \AzureOss\Storage\Blob\BlobServiceClient::fromConnectionString($connectionString)
            ->getContainerClient('maho-test')
            ->createIfNotExists();

        $definition = new MountDefinition(name: 'media', adapterType: 'azure', adapterOptions: [
            'container' => 'maho-test',
            'connection_string' => $connectionString,
            'prefix' => 'run-' . uniqid(),
        ]);
        $this->mount = new Mount('media', new AdapterFactory()->create($definition));
    });

    afterEach(function (): void {
        if (isset($this->mount)) {
            $this->mount->deleteDirectory('');
        }
    });

    it('is a remote mount that round-trips a file', function (): void {
        $this->mount->write('a/b.txt', 'hello');

        expect($this->mount->isLocal())->toBeFalse()
            ->and($this->mount->fileExists('a/b.txt'))->toBeTrue()
            ->and($this->mount->read('a/b.txt'))->toBe('hello');
    });

    it('moves by copy plus delete and fails on a missing source', function (): void {
        $this->mount->write('from.txt', 'x');

        $this->mount->move('from.txt', 'to.txt');

        expect($this->mount->fileExists('from.txt'))->toBeFalse()
            ->and($this->mount->read('to.txt'))->toBe('x')
            ->and(fn() => $this->mount->move('missing.txt', 'other.txt'))->toThrow(UnableToMoveFile::class);
    });

    it('returns the folders too in a deep listing, unlike S3', function (): void {
        $this->mount->write('d/e/f.txt', 'x');

        $items = $this->mount->listContents('', true)->toArray();
        $paths = fn(bool $files): array => array_values(array_map(
            fn($item) => $item->path(),
            array_filter($items, fn($item) => $item->isFile() === $files),
        ));

        expect($paths(true))->toBe(['d/e/f.txt'])
            ->and($paths(false))->toBe(['d', 'd/e']);
    });

    it('writes a file in one step with moveAtomic()', function (): void {
        $this->mount->moveAtomic('feed.xml', '<feed/>');

        expect($this->mount->read('feed.xml'))->toBe('<feed/>');
    });

    it('signs a temporary url that a plain GET can fetch', function (): void {
        $this->mount->write('t.txt', 'signed');

        $url = $this->mount->temporaryUrl('t.txt', new DateTimeImmutable('+5 minutes'));

        expect(file_get_contents($url))->toBe('signed');
    });
})->group('storage-azure');
