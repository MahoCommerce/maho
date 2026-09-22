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

    it('requires a path for the local adapter', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('local')))
            ->toThrow(StorageException::class, 'needs a <path>');
    });

    it('builds the s3 adapter when the package is installed', function (): void {
        $adapter = new AdapterFactory()->create(storageFactoryDefinition('s3', ['bucket' => 'b', 'region' => 'eu-west-1']));

        expect($adapter)->toBeInstanceOf(AwsS3V3Adapter::class);
    });

    it('requires a bucket for s3', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('s3')))
            ->toThrow(StorageException::class, 'needs a <bucket>');
    });

    it('requires key and secret together for s3', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('s3', ['bucket' => 'b', 'key' => 'k'])))
            ->toThrow(StorageException::class, 'both <key> and <secret>');
    });

    it('names the composer package when the adapter package is missing', function (): void {
        expect(AdapterNotInstalledException::forMount('media', 's3', 'league/flysystem-aws-s3-v3')->getMessage())
            ->toBe('Storage mount "media" uses the "s3" adapter, which requires the league/flysystem-aws-s3-v3 Composer package. Install it with: composer require league/flysystem-aws-s3-v3');
    });

    it('rejects an unknown type', function (): void {
        expect(fn() => new AdapterFactory()->create(storageFactoryDefinition('floppy')))
            ->toThrow(StorageException::class, 'unknown adapter type "floppy"');
    });
});
