<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage\Adapter;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use Maho\Storage\AdapterFactoryInterface;
use Maho\Storage\MountDefinition;
use Maho\Storage\StorageException;

/**
 * S3 and S3-compatible buckets (MinIO, Cloudflare R2, DigitalOcean Spaces, ...).
 *
 * Options: `bucket` (required), `prefix`, `region` (default us-east-1),
 * `endpoint`, `key` and `secret` (both or none: none uses the SDK credential
 * chain, so an instance role works), `use_path_style_endpoint` (MinIO needs it).
 */
final class S3 implements AdapterFactoryInterface
{
    #[\Override]
    public function create(MountDefinition $definition): FilesystemAdapter
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

        return new AwsS3V3Adapter(new S3Client($config), $bucket, $definition->option('prefix') ?? '');
    }
}
