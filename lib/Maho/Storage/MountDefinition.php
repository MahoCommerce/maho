<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\Visibility;
use Symfony\Component\Filesystem\Path;

/**
 * One mount as declared under `<global><storage><mounts><{name}>`.
 *
 * ```xml
 * <media>
 *     <dir>media</dir>                     <!-- any type Mage::getBaseDir() answers; absent means the Maho root -->
 *     <path>catalog</path>                 <!-- relative to <dir>, or absolute; absent means <dir> itself -->
 *     <url_type>media</url_type>           <!-- media or web: the store base URL that serves this path -->
 *     <public_url>https://cdn.example.com/media/</public_url>   <!-- explicit prefix, wins over url_type -->
 *     <visibility>public</visibility>      <!-- default visibility for every write -->
 *     <adapter>                            <!-- absent means local -->
 *         <type>s3</type>
 *         <bucket>my-store</bucket>
 *     </adapter>
 * </media>
 * ```
 */
final readonly class MountDefinition
{
    public const ADAPTER_LOCAL = 'local';

    private const URL_TYPES = [\Mage_Core_Model_Store::URL_TYPE_MEDIA, \Mage_Core_Model_Store::URL_TYPE_WEB];

    /**
     * @param array<string, mixed> $adapterOptions every child of <adapter> except <type>
     */
    public function __construct(
        public string $name,
        public ?string $path = null,
        public ?string $urlType = null,
        public ?string $publicUrl = null,
        public ?string $visibility = null,
        public string $adapterType = self::ADAPTER_LOCAL,
        public array $adapterOptions = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new StorageException(sprintf('Invalid storage mount name "%s": use lowercase letters, digits and underscores.', $name));
        }
        if ($adapterType === self::ADAPTER_LOCAL && ($path === null || $path === '')) {
            throw new StorageException(sprintf('Storage mount "%s" uses the local adapter and needs a <path>.', $name));
        }
        if ($urlType !== null && !in_array($urlType, self::URL_TYPES, true)) {
            throw new StorageException(sprintf('Storage mount "%s" has an invalid <url_type> "%s": use %s.', $name, $urlType, implode(' or ', self::URL_TYPES)));
        }
        if ($visibility !== null && !in_array($visibility, [Visibility::PUBLIC, Visibility::PRIVATE], true)) {
            throw new StorageException(sprintf('Storage mount "%s" has an invalid <visibility> "%s": use public or private.', $name, $visibility));
        }
    }

    /**
     * <dir> names a directory that Maho already knows, for example var or media.
     * Maho resolves a relative <path> against that directory. Without <dir>,
     * Maho resolves it against the Maho root. An empty element reads as absent.
     *
     * @param \Closure(string=): string $baseDir Mage::getBaseDir(...)
     */
    public static function fromElement(string $name, \Mage_Core_Model_Config_Element $node, \Closure $baseDir): self
    {
        $dir = self::text($node, 'dir');
        $base = $dir === null ? $baseDir() : $baseDir($dir);

        $path = self::text($node, 'path');
        if ($path !== null) {
            $path = Path::makeAbsolute($path, $base);
        } elseif ($dir !== null) {
            $path = $base;
        }

        $adapterType = self::ADAPTER_LOCAL;
        $adapterOptions = [];
        if (isset($node->adapter)) {
            $adapter = $node->adapter;
            if (!$adapter instanceof \Mage_Core_Model_Config_Element || !$adapter->hasChildren()) {
                throw new StorageException(sprintf('Storage mount "%s": <adapter> must be a block with a <type> child, not a plain value.', $name));
            }
            $adapterType = self::text($adapter, 'type')
                ?? throw new StorageException(sprintf('Storage mount "%s": <adapter> has no <type>.', $name));
            $adapterOptions = $adapter->asArray();
            unset($adapterOptions['type']);
        }

        return new self(
            name: $name,
            path: $path,
            urlType: self::text($node, 'url_type'),
            publicUrl: self::text($node, 'public_url'),
            visibility: self::text($node, 'visibility'),
            adapterType: $adapterType,
            adapterOptions: $adapterOptions,
        );
    }

    public function option(string $key): ?string
    {
        $value = $this->adapterOptions[$key] ?? null;
        if (is_array($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Reads an adapter option as an octal mode, for example 0666. */
    public function mode(string $key, int $default): int
    {
        $value = $this->option($key);
        if ($value === null) {
            return $default;
        }
        if (preg_match('/^0?[0-7]{3}$/', $value) !== 1) {
            throw new StorageException(sprintf('Storage mount "%s" has an invalid <%s> "%s": use an octal mode such as 0666.', $this->name, $key, $value));
        }

        return (int) octdec($value);
    }

    /** Same truth table as Mage_Core_Model_Config_Element::is(): empty, "0", "false" and "off" are false. */
    public function flag(string $key): bool
    {
        $value = strtolower((string) $this->option($key));

        return $value !== '' && $value !== '0' && $value !== 'false' && $value !== 'off';
    }

    private static function text(\Mage_Core_Model_Config_Element $node, string $child): ?string
    {
        if (!isset($node->{$child})) {
            return null;
        }
        $value = trim((string) $node->{$child});

        return $value === '' ? null : $value;
    }
}
