<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\FilesystemAdapter;

/**
 * Resolves `<adapter><type>` to a Flysystem adapter.
 *
 * Built-in types ship with Maho. A remote type needs its Composer package, and
 * a missing one fails with the exact `composer require` line. Any other type
 * is the name of a class implementing AdapterFactoryInterface.
 */
final class AdapterFactory
{
    /** @var array<string, array{factory: class-string<AdapterFactoryInterface>, requires: class-string|null, package: string|null}> */
    public const BUILT_IN = [
        MountDefinition::ADAPTER_LOCAL => ['factory' => Adapter\Local::class, 'requires' => null, 'package' => null],
        's3' => ['factory' => Adapter\S3::class, 'requires' => \League\Flysystem\AwsS3V3\AwsS3V3Adapter::class, 'package' => 'league/flysystem-aws-s3-v3'],
    ];

    /** Names kept for the adapters Maho does not ship a factory for yet. */
    public const RESERVED = [
        'gcs' => 'league/flysystem-google-cloud-storage',
        'azure' => 'league/flysystem-azure-blob-storage',
        'sftp' => 'league/flysystem-sftp-v3',
        'ftp' => 'league/flysystem-ftp',
    ];

    /**
     * @internal the map is overridable for tests only
     * @param array<string, array{factory: class-string<AdapterFactoryInterface>, requires: class-string|null, package: string|null}> $builtIn
     */
    public function __construct(private readonly array $builtIn = self::BUILT_IN) {}

    public function create(MountDefinition $definition): FilesystemAdapter
    {
        $type = $definition->adapterType;

        if (isset($this->builtIn[$type])) {
            $entry = $this->builtIn[$type];
            if ($entry['requires'] !== null && !class_exists($entry['requires'])) {
                throw AdapterNotInstalledException::forMount($definition->name, $type, (string) $entry['package']);
            }

            return new $entry['factory']()->create($definition);
        }

        if (isset(self::RESERVED[$type])) {
            $package = self::RESERVED[$type];
            throw new StorageException(sprintf(
                'Storage mount "%s" uses the "%s" adapter. Maho ships no factory for it yet: install %s (composer require %s) and set <type> to a class that implements %s.',
                $definition->name,
                $type,
                $package,
                $package,
                AdapterFactoryInterface::class,
            ));
        }

        if (!class_exists($type)) {
            throw new StorageException(sprintf(
                'Storage mount "%s" uses unknown adapter type "%s". Built-in types: %s. Any other value must be a class that implements %s.',
                $definition->name,
                $type,
                implode(', ', array_keys($this->builtIn)),
                AdapterFactoryInterface::class,
            ));
        }

        $factory = new $type();
        if (!$factory instanceof AdapterFactoryInterface) {
            throw new StorageException(sprintf('Storage mount "%s": %s must implement %s.', $definition->name, $type, AdapterFactoryInterface::class));
        }

        return $factory->create($definition);
    }
}
