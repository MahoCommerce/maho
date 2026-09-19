<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\AdapterFactory;
use Maho\Storage\AdapterFactoryInterface;
use Maho\Storage\AdapterNotInstalledException;
use Maho\Storage\MountDefinition;
use Maho\Storage\StorageException;

final class StorageTestCustomFactory implements AdapterFactoryInterface
{
    public static ?MountDefinition $received = null;

    #[\Override]
    public function create(MountDefinition $definition): FilesystemAdapter
    {
        self::$received = $definition;

        return new LocalFilesystemAdapter(sys_get_temp_dir());
    }
}

final class StorageTestNotAFactory {}

function storageFactoryDefinition(string $type, array $options = [], ?string $path = null): MountDefinition
{
    return new MountDefinition(name: 'media', path: $path, adapterType: $type, adapterOptions: $options);
}

describe('Maho\Storage\AdapterFactory', function () {
    it('builds the local adapter', function (): void {
        $adapter = (new AdapterFactory())->create(storageFactoryDefinition('local', path: sys_get_temp_dir()));

        expect($adapter)->toBeInstanceOf(LocalFilesystemAdapter::class);
    });

    it('builds the s3 adapter when the package is installed', function (): void {
        $adapter = (new AdapterFactory())->create(storageFactoryDefinition('s3', ['bucket' => 'b', 'region' => 'eu-west-1']));

        expect($adapter)->toBeInstanceOf(AwsS3V3Adapter::class);
    });

    it('requires a bucket for s3', function (): void {
        expect(fn() => (new AdapterFactory())->create(storageFactoryDefinition('s3')))
            ->toThrow(StorageException::class, 'needs a <bucket>');
    });

    it('requires key and secret together for s3', function (): void {
        expect(fn() => (new AdapterFactory())->create(storageFactoryDefinition('s3', ['bucket' => 'b', 'key' => 'k'])))
            ->toThrow(StorageException::class, 'both <key> and <secret>');
    });

    it('names the composer package when the adapter package is missing', function (): void {
        $factory = new AdapterFactory([
            's3' => ['factory' => \Maho\Storage\Adapter\S3::class, 'requires' => 'Vendor\Missing\Adapter', 'package' => 'vendor/missing-adapter'],
        ]);

        expect(fn() => $factory->create(storageFactoryDefinition('s3', ['bucket' => 'b'])))
            ->toThrow(
                AdapterNotInstalledException::class,
                'Storage mount "media" uses the "s3" adapter, which requires the vendor/missing-adapter Composer package. Install it with: composer require vendor/missing-adapter',
            );
    });

    it('names the composer package for a reserved adapter', function (): void {
        expect(fn() => (new AdapterFactory())->create(storageFactoryDefinition('gcs')))
            ->toThrow(StorageException::class, 'composer require league/flysystem-google-cloud-storage');
    });

    it('uses a custom factory class', function (): void {
        $definition = storageFactoryDefinition(StorageTestCustomFactory::class, ['foo' => 'bar']);

        $adapter = (new AdapterFactory())->create($definition);

        expect($adapter)->toBeInstanceOf(LocalFilesystemAdapter::class)
            ->and(StorageTestCustomFactory::$received)->toBe($definition);
    });

    it('rejects a class that is not a factory', function (): void {
        expect(fn() => (new AdapterFactory())->create(storageFactoryDefinition(StorageTestNotAFactory::class)))
            ->toThrow(StorageException::class, 'must implement');
    });

    it('rejects an unknown type', function (): void {
        expect(fn() => (new AdapterFactory())->create(storageFactoryDefinition('floppy')))
            ->toThrow(StorageException::class, 'unknown adapter type "floppy"');
    });
});
