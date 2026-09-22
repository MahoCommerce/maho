<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Builds the Flysystem adapter behind a mount from its `<adapter>` block.
 *
 * Maho builds local, s3, gcs and azure. Maho installs only the local adapter.
 * Maho suggests the Composer package of every remote type and never installs
 * one, so a missing package fails with the exact `composer require` line. A
 * module that needs another adapter builds its own Mount and registers it
 * with MountRegistry::register().
 */
final class AdapterFactory
{
    private const REMOTE_PACKAGES = [
        's3' => ['League\Flysystem\AwsS3V3\AwsS3V3Adapter', 'league/flysystem-aws-s3-v3'],
        'gcs' => ['League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter', 'league/flysystem-google-cloud-storage'],
        'azure' => ['AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter', 'azure-oss/storage-blob-flysystem'],
    ];

    public function create(MountDefinition $definition): FilesystemAdapter
    {
        return match ($definition->adapterType) {
            MountDefinition::ADAPTER_LOCAL => $this->createLocal($definition),
            's3' => $this->createS3($definition),
            'gcs' => $this->createGcs($definition),
            'azure' => $this->createAzure($definition),
            default => throw new StorageException(sprintf(
                'Storage mount "%s" uses unknown adapter type "%s". Maho builds local, s3, gcs and azure. An S3-compatible store uses s3 with an <endpoint>. For anything else, build the Mount in your module and pass it to %s::register().',
                $definition->name,
                $definition->adapterType,
                MountRegistry::class,
            )),
        };
    }

    private function createLocal(MountDefinition $definition): FilesystemAdapter
    {
        return new LocalFilesystemAdapter((string) $definition->path, lazyRootCreation: true);
    }

    /**
     * S3 and S3-compatible buckets (MinIO, Cloudflare R2, DigitalOcean Spaces, ...).
     *
     * Options: `bucket` (required), `prefix`, `region` (default us-east-1),
     * `endpoint`, `key` and `secret` (both or none: none uses the SDK credential
     * chain, so an instance role works), `use_path_style_endpoint` (MinIO needs it).
     */
    private function createS3(MountDefinition $definition): FilesystemAdapter
    {
        $bucket = $definition->option('bucket')
            ?? throw new StorageException(sprintf('Storage mount "%s" uses the s3 adapter and needs a <bucket>.', $definition->name));

        $key = $definition->option('key');
        $secret = $definition->option('secret');
        if (($key === null) !== ($secret === null)) {
            throw new StorageException(sprintf('Storage mount "%s": set both <key> and <secret>, or neither.', $definition->name));
        }

        $config = [
            'version' => 'latest',
            'region' => $definition->option('region') ?? 'us-east-1',
        ];
        if ($key !== null && $secret !== null) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }
        $endpoint = $definition->option('endpoint');
        if ($endpoint !== null) {
            $config['endpoint'] = $endpoint;
        }
        if ($definition->flag('use_path_style_endpoint')) {
            $config['use_path_style_endpoint'] = true;
        }

        $this->requirePackage($definition, 's3');

        $client = new \Aws\S3\S3Client($config); // @phpstan-ignore class.notFound

        return new \League\Flysystem\AwsS3V3\AwsS3V3Adapter($client, $bucket, $definition->option('prefix') ?? ''); // @phpstan-ignore class.notFound, return.type
    }

    /**
     * Google Cloud Storage.
     *
     * Options: `bucket` (required), `prefix`, `project_id`, `key_file` (the path
     * to a service account JSON file). Without `key_file` the Google client uses
     * its own credential chain, so a workload identity works.
     */
    private function createGcs(MountDefinition $definition): FilesystemAdapter
    {
        $bucket = $definition->option('bucket')
            ?? throw new StorageException(sprintf('Storage mount "%s" uses the gcs adapter and needs a <bucket>.', $definition->name));

        $config = [];
        $projectId = $definition->option('project_id');
        if ($projectId !== null) {
            $config['projectId'] = $projectId;
        }
        $keyFile = $definition->option('key_file');
        if ($keyFile !== null) {
            $config['keyFilePath'] = $keyFile;
        }

        $this->requirePackage($definition, 'gcs');

        $client = new \Google\Cloud\Storage\StorageClient($config); // @phpstan-ignore class.notFound

        return new \League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter($client->bucket($bucket), $definition->option('prefix') ?? ''); // @phpstan-ignore class.notFound, class.notFound, return.type
    }

    /**
     * Azure Blob Storage.
     *
     * Options: `container` (required), `connection_string` (required), `prefix`.
     * Set `public_container` when the container serves files without a signature,
     * so publicUrl() returns a plain URL instead of a signed one.
     */
    private function createAzure(MountDefinition $definition): FilesystemAdapter
    {
        $container = $definition->option('container')
            ?? throw new StorageException(sprintf('Storage mount "%s" uses the azure adapter and needs a <container>.', $definition->name));
        $connectionString = $definition->option('connection_string')
            ?? throw new StorageException(sprintf('Storage mount "%s" uses the azure adapter and needs a <connection_string>.', $definition->name));

        $this->requirePackage($definition, 'azure');

        $service = \AzureOss\Storage\Blob\BlobServiceClient::fromConnectionString($connectionString); // @phpstan-ignore class.notFound

        // @phpstan-ignore class.notFound, return.type
        return new \AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter(
            $service->getContainerClient($container),
            $definition->option('prefix') ?? '',
            isPublicContainer: $definition->flag('public_container'),
        );
    }

    private function requirePackage(MountDefinition $definition, string $type): void
    {
        [$class, $package] = self::REMOTE_PACKAGES[$type];
        if (!class_exists($class)) { // @phpstan-ignore function.impossibleType
            throw AdapterNotInstalledException::forMount($definition->name, $type, $package);
        }
    }
}
