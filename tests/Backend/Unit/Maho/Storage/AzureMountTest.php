<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

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

    it('lists the files at any depth and leaves the folders out', function (): void {
        $this->mount->write('d/e/f.txt', 'x');

        expect(array_map(fn($item) => $item->path(), $this->mount->listFiles()->toArray()))->toBe(['d/e/f.txt']);
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
