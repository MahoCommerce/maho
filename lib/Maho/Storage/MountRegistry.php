<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator;
use Maho\Storage\Url\StoreUrlGenerator;

/**
 * The mounts declared under `<global><storage><mounts>`, built on first use.
 *
 * A module declares its mount in config.xml with a local default path.
 * local.xml overrides any mount by name, core or custom, and points it at a
 * remote adapter. Only files shared between nodes go through a mount: cache,
 * session, log, tmp and locks stay on plain PHP filesystem calls.
 */
final class MountRegistry
{
    public const XML_PATH_MOUNTS = 'global/storage/mounts';

    /** @var array<string, MountDefinition>|null */
    private static ?array $definitions = null;

    /** @var array<string, Mount> */
    private static array $mounts = [];

    /**
     * @throws UnknownMountException
     * @throws StorageException when the mount is misconfigured or its adapter package is missing
     */
    public static function get(string $name): Mount
    {
        if (isset(self::$mounts[$name])) {
            return self::$mounts[$name];
        }

        $definition = self::definitions()[$name] ?? throw UnknownMountException::forName($name, self::names());

        return self::$mounts[$name] = self::build($definition);
    }

    public static function has(string $name): bool
    {
        return isset(self::$mounts[$name]) || isset(self::definitions()[$name]);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::$mounts), array_keys(self::definitions()))));
    }

    /**
     * @return array<string, Mount>
     */
    public static function all(): array
    {
        foreach (self::names() as $name) {
            self::get($name);
        }

        return self::$mounts;
    }

    /** Replaces the mount with that name for the rest of the request, for tests and runtime registration. */
    public static function register(Mount $mount): void
    {
        self::$mounts[$mount->name()] = $mount;
    }

    public static function reset(): void
    {
        self::$definitions = null;
        self::$mounts = [];
    }

    /**
     * @return array<string, MountDefinition>
     */
    private static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $definitions = [];
        $config = \Mage::getConfig();
        $node = $config?->getNode(self::XML_PATH_MOUNTS);
        if ($node instanceof \Mage_Core_Model_Config_Element) {
            $baseDir = \Mage::getBaseDir();
            foreach ($node->children() as $name => $child) {
                $definitions[(string) $name] = MountDefinition::fromElement((string) $name, $child, $baseDir);
            }
        }

        return self::$definitions = $definitions;
    }

    private static function build(MountDefinition $definition): Mount
    {
        $adapter = new AdapterFactory()->create($definition);

        $urlGenerator = null;
        if ($definition->publicUrl !== null) {
            $urlGenerator = new PrefixPublicUrlGenerator($definition->publicUrl);
        } elseif ($definition->urlType !== null) {
            $urlGenerator = new StoreUrlGenerator($definition->urlType);
        }

        $config = [];
        if ($definition->visibility !== null) {
            $config['visibility'] = $definition->visibility;
        }

        return new Mount(
            name: $definition->name,
            adapter: $adapter,
            localRoot: $definition->adapterType === MountDefinition::ADAPTER_LOCAL ? $definition->path : null,
            publicUrlGenerator: $urlGenerator,
            config: $config,
        );
    }
}
