<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

/**
 * Builds the Flysystem adapter behind a mount from its `<adapter>` block.
 *
 * Maho ships local and s3. A module that needs another one builds the adapter
 * itself and calls MountRegistry::register().
 */
final class AdapterFactory
{
    public function create(MountDefinition $definition): FilesystemAdapter
    {
        return match ($definition->adapterType) {
            MountDefinition::ADAPTER_LOCAL => $this->createLocal($definition),
            's3' => $this->createS3($definition),
            default => throw new StorageException(sprintf(
                'Storage mount "%s" uses unknown adapter type "%s". Maho builds local and s3. An S3-compatible store needs an <endpoint>. For anything else, build the adapter in your module and call %s::register().',
                $definition->name,
                $definition->adapterType,
                MountRegistry::class,
            )),
        };
    }

    private function createLocal(MountDefinition $definition): FilesystemAdapter
    {
        if ($definition->path === null) {
            throw new StorageException(sprintf('Storage mount "%s" uses the local adapter and needs a <path>.', $definition->name));
        }

        // Same modes the rest of Maho writes with (Maho\File\Uploader chmods 0666
        // and mkdirs 0777), so a mount and legacy code agree on one disk.
        $visibility = PortableVisibilityConverter::fromArray([
            'file' => ['public' => 0666, 'private' => 0600],
            'dir' => ['public' => 0777, 'private' => 0700],
        ], Visibility::PUBLIC);

        return new LocalFilesystemAdapter($definition->path, $visibility);
    }

    /**
     * S3 and S3-compatible buckets (MinIO, Cloudflare R2, DigitalOcean Spaces,
     * Google Cloud Storage through its S3 API, ...). Each one needs an `endpoint`.
     *
     * Options: `bucket` (required), `prefix`, `region` (default us-east-1),
     * `endpoint`, `key` and `secret` (both or none: none uses the SDK credential
     * chain, so an instance role works), `use_path_style_endpoint` (MinIO needs it).
     */
    private function createS3(MountDefinition $definition): FilesystemAdapter
    {
        if (!class_exists(AwsS3V3Adapter::class)) {
            throw AdapterNotInstalledException::forMount($definition->name, 's3', 'league/flysystem-aws-s3-v3');
        }

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

        return new AwsS3V3Adapter(new S3Client($config), $bucket, $definition->option('prefix') ?? '');
    }
}
