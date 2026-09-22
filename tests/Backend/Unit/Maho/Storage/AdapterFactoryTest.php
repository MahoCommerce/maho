<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\AdapterFactory;
use Maho\Storage\AdapterNotInstalledException;
use Maho\Storage\MountDefinition;
use Maho\Storage\StorageException;

function storageFactoryDefinition(string $type, array $options = [], ?string $path = null): MountDefinition
{
    return new MountDefinition(name: 'media', path: $path, adapterType: $type, adapterOptions: $options);
}

describe(\Maho\Storage\AdapterFactory::class, function () {
    it('builds the local adapter', function (): void {
        $adapter = new AdapterFactory()->create(storageFactoryDefinition('local', path: sys_get_temp_dir()));

        expect($adapter)->toBeInstanceOf(LocalFilesystemAdapter::class);
    });

    it('builds the s3 adapter when the package is installed', function (): void {
        $adapter = new AdapterFactory()->create(storageFactoryDefinition('s3', ['bucket' => 'b', 'region' => 'eu-west-1']));

        expect($adapter)->toBeInstanceOf(AwsS3V3Adapter::class);
    })->skip(fn() => !class_exists(AwsS3V3Adapter::class), 'league/flysystem-aws-s3-v3 is not installed');

    // Each row checks its own package: installing one must not silence the others.
    it('names the composer package of every adapter Maho does not install', function (string $type, array $options, string $package, string $class): void {
        if (class_exists($class)) {
            $this->markTestSkipped($package . ' is installed');
        }

        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition($type, $options)))
            ->toThrow(AdapterNotInstalledException::class, 'composer require ' . $package);
    })->with([
        ['s3', ['bucket' => 'b'], 'league/flysystem-aws-s3-v3', 'League\Flysystem\AwsS3V3\AwsS3V3Adapter'],
        ['gcs', ['bucket' => 'b'], 'league/flysystem-google-cloud-storage', 'League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter'],
        ['azure', ['container' => 'c', 'connection_string' => 'x'], 'azure-oss/storage-blob-flysystem', 'AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter'],
    ]);

    it('requires a bucket for gcs', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('gcs')))
            ->toThrow(StorageException::class, 'needs a <bucket>');
    });

    it('requires a container and a connection string for azure', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('azure')))
            ->toThrow(StorageException::class, 'needs a <container>');
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('azure', ['container' => 'c'])))
            ->toThrow(StorageException::class, 'needs a <connection_string>');
    });

    it('requires a bucket for s3', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('s3')))
            ->toThrow(StorageException::class, 'needs a <bucket>');
    });

    it('requires key and secret together for s3', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('s3', ['bucket' => 'b', 'key' => 'k'])))
            ->toThrow(StorageException::class, 'both <key> and <secret>');
    });

    it('rejects an unknown type', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('floppy')))
            ->toThrow(StorageException::class, 'unknown adapter type "floppy"');
    });
});
