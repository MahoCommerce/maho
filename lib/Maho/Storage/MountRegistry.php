<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator;
use Maho\Storage\Url\StoreBaseUrlGenerator;

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

    /** @var array<string, MountDefinition> */
    private static array $definitions = [];

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

        $definition = self::definition($name) ?? throw UnknownMountException::forName($name, self::names());

        return self::$mounts[$name] = self::build($definition);
    }

    /**
     * The mount on the local folder that $name uses without an adapter block in local.xml, as the
     * module declares it. Null when the declaration names no folder. storage:migrate copies from it.
     */
    public static function getDeclaredLocalMount(string $name): ?Mount
    {
        $definition = self::definition($name) ?? throw UnknownMountException::forName($name, self::names());
        if ($definition->path === null || $definition->path === '') {
            return null;
        }

        return new Mount(
            name: $definition->name,
            adapter: new AdapterFactory()->create(new MountDefinition($definition->name, $definition->path)),
            localRoot: $definition->path,
        );
    }

    public static function has(string $name): bool
    {
        return isset(self::$mounts[$name]) || in_array($name, self::declaredNames(), true);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::$mounts), self::declaredNames())));
    }

    /** Replaces the mount with that name for the rest of the request. Tests use it. */
    public static function register(Mount $mount): void
    {
        self::$mounts[$mount->name()] = $mount;
    }

    public static function reset(): void
    {
        self::$definitions = [];
        self::$mounts = [];
    }

    /**
     * The declaration of one mount, built when its name is first asked for, so a bad
     * declaration of one module breaks only its own mount.
     */
    private static function definition(string $name): ?MountDefinition
    {
        if (isset(self::$definitions[$name])) {
            return self::$definitions[$name];
        }

        $child = \Mage::getConfig()?->getNode(self::XML_PATH_MOUNTS . '/' . $name);
        if (!$child instanceof \Mage_Core_Model_Config_Element) {
            return null;
        }

        return self::$definitions[$name] = MountDefinition::fromElement($name, $child, \Mage::getBaseDir(...));
    }

    /**
     * @return list<string>
     */
    private static function declaredNames(): array
    {
        $node = \Mage::getConfig()?->getNode(self::XML_PATH_MOUNTS);
        if (!$node instanceof \Mage_Core_Model_Config_Element) {
            return [];
        }

        return array_map(strval(...), array_keys(iterator_to_array($node->children(), true)));
    }

    private static function build(MountDefinition $definition): Mount
    {
        $adapter = new AdapterFactory()->create($definition);

        // A mount with a url_type follows the store base URL, also on a bucket, so its URLs
        // agree with getBaseUrl(). Only a mount with neither setting keeps the adapter URL.
        $urlGenerator = null;
        if ($definition->publicUrl !== null) {
            $urlGenerator = new PrefixPublicUrlGenerator($definition->publicUrl);
        } elseif ($definition->urlType !== null) {
            $urlGenerator = new StoreBaseUrlGenerator($definition->urlType);
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
