<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

/**
 * Central registry of API resource permissions.
 *
 * Single source of truth for the valid `resource/operation` permission IDs and
 * their grouping/labels, consumed by the admin role editor UI and the
 * orphaned-rule cleanup in `Mage_Api_Model_Resource_Rules`.
 *
 * Authorization enforcement itself does NOT go through this class: each API
 * Platform operation declares its required permission literally in its
 * `security:` expression (e.g. `is_granted('products/write')`), which
 * API Platform evaluates for both REST and GraphQL and routes to
 * `ApiUserVoter`. This registry only describes which permission IDs exist.
 *
 * Backed by `vendor/composer/maho_api_permissions.php`, compiled at
 * `composer dump-autoload` from `#[Maho\Config\ApiResource]` attributes
 * (subclass of API Platform's `ApiResource`) on resource DTO classes.
 *
 * Run `composer dump-autoload` after adding/modifying an attribute. If that file
 * is missing or empty when the registry is first read, it is rebuilt in-process
 * once so the admin role editor never silently renders an empty permission tree.
 */
class ApiPermissionRegistry
{
    /**
     * Path to the compiled permissions file, relative to the Maho project root.
     */
    private const COMPILED_FILE = '/vendor/composer/maho_api_permissions.php';

    /**
     * Cached compiled data. `null` until first `load()`.
     *
     * @var array{
     *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
     *     publicRead: list<string>,
     *     customerScoped: array<string, string>,
     * }|null
     */
    private static ?array $compiled = null;

    /**
     * Guard so a still-broken install pays for at most one class-map scan per
     * process instead of one per request.
     */
    private static bool $compileAttempted = false;

    /**
     * Lazy-load the compiled permissions file, rebuilding it in-process when it
     * is missing or empty. Returns an empty registry and logs a warning when the
     * rebuild is impossible too; the role editor then shows no grantable
     * permissions, which is the safe failure mode.
     *
     * @return array{
     *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
     *     publicRead: list<string>,
     *     customerScoped: array<string, string>,
     * }
     */
    private static function load(): array
    {
        if (self::$compiled !== null) {
            return self::$compiled;
        }

        $basePath = defined('BP') ? BP : dirname(__DIR__, 7);
        $path = $basePath . self::COMPILED_FILE;
        $data = self::read($path);

        // The composer plugin skips the API permission compile whenever it cannot
        // autoload the API Platform classes, which leaves an install with no file
        // at all (or a stale one compiled before the API modules were scannable).
        // Rebuilding here keeps the role editor usable instead of rendering three
        // blank sections with no explanation.
        if (($data === null || $data['resources'] === []) && self::recompile($basePath)) {
            $data = self::read($path) ?? $data;
        }

        if ($data === null) {
            \Mage::log(
                'ApiPermissionRegistry: ' . $path . ' is missing and could not be compiled, run '
                . '`composer dump-autoload`. Falling back to empty registry; the role editor will '
                . 'show no grantable permissions.',
                \Mage::LOG_WARNING,
            );
            $data = [
                'resources' => [],
                'publicRead' => [],
                'customerScoped' => [],
            ];
        }

        return self::$compiled = $data;
    }

    /**
     * Read the compiled file, or `null` when it is absent or unusable.
     *
     * @return array{
     *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
     *     publicRead: list<string>,
     *     customerScoped: array<string, string>,
     * }|null
     */
    private static function read(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $data = include $path;
        if (!is_array($data)) {
            return null;
        }

        /** @var array{
         *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
         *     publicRead: list<string>,
         *     customerScoped: array<string, string>,
         * } $data */
        $data += ['resources' => [], 'publicRead' => [], 'customerScoped' => []];
        return $data;
    }

    /**
     * Recompile the permission map through the composer plugin's runtime entry
     * point, the same one the admin cache page uses. Returns whether the file
     * is worth re-reading.
     */
    private static function recompile(string $basePath): bool
    {
        if (self::$compileAttempted) {
            return false;
        }
        self::$compileAttempted = true;

        // Mirrors the plugin's own guard: the parent class is checked first so
        // that resolving Maho\Config\ApiResource cannot fatal on an install that
        // has replaced api-platform/core out.
        if (!class_exists(\ApiPlatform\Metadata\ApiResource::class)
            || !class_exists(\Maho\Config\ApiResource::class)
            || !class_exists(\Maho\ComposerPlugin\ApiPermissionCompiler::class)
        ) {
            return false;
        }

        try {
            \Maho\ComposerPlugin\ApiPermissionCompiler::compileRuntime($basePath . '/vendor/composer');
        } catch (\Throwable $e) {
            // A read-only vendor/ is a legitimate deployment, so this stays a
            // warning and the caller falls back to the empty registry.
            \Mage::log(
                'ApiPermissionRegistry: rebuilding the permission map failed, run '
                . '`composer dump-autoload`. ' . $e->getMessage(),
                \Mage::LOG_WARNING,
            );
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($basePath . self::COMPILED_FILE, true);
        }
        return true;
    }

    /**
     * Get full resource definitions for admin UI
     *
     * @return array<string, array{label: string, section: string, operations: array<string, string>}>
     */
    public function getResources(): array
    {
        return self::load()['resources'];
    }

    /**
     * Flat list of valid permission IDs, one per resource/operation pair.
     * Format: "resource/operation" e.g. "products/read", "orders/write".
     * Single source of truth for the role editor and orphaned-rule cleanup.
     *
     * @return list<string>
     */
    public function getPermissionIds(): array
    {
        $ids = [];
        foreach (self::load()['resources'] as $resourceId => $config) {
            foreach (array_keys($config['operations']) as $operation) {
                $ids[] = $resourceId . '/' . $operation;
            }
        }
        return $ids;
    }

    /**
     * Get resources that have public read access (no auth required).
     *
     * @return array<string, string> resource ID => label
     */
    public function getPublicReadResources(): array
    {
        $data = self::load();
        $result = [];
        foreach ($data['publicRead'] as $resourceId) {
            if (array_key_exists($resourceId, $data['resources'])) {
                $result[$resourceId] = $data['resources'][$resourceId]['label'];
            }
        }
        return $result;
    }

    /**
     * Get resources that use customer JWT tokens (session-bound).
     *
     * @return array<string, string> resource ID => description
     */
    public function getCustomerResources(): array
    {
        return self::load()['customerScoped'];
    }

    /**
     * Check if a resource has public read access
     */
    public function isPublicRead(string $resourceId): bool
    {
        return in_array($resourceId, self::load()['publicRead'], true);
    }

    /**
     * Get service-account permissions grouped by section for the admin role editor UI.
     *
     * Returns only resources/operations relevant to API service accounts:
     * - Skips read-only public resources (no permissions needed)
     * - Skips read operations on publicly-readable resources
     *
     * @return array<string, array<string, array{label: string, operations: array<string, string>}>>
     */
    public function getServicePermissionsBySection(): array
    {
        $data = self::load();
        /** @var array<string, array<string, array{label: string, operations: array<string, string>}>> $grouped */
        $grouped = [];
        foreach ($data['resources'] as $resourceId => $config) {
            $operations = $config['operations'];

            // Skip resources that are entirely public read-only (all resources have 'read')
            $isPublicRead = in_array($resourceId, $data['publicRead'], true);
            if ($isPublicRead && count($operations) === 1) {
                continue;
            }

            // For public-read resources, exclude the read operation from the tree
            if ($isPublicRead) {
                unset($operations['read']);
            }

            if (empty($operations)) {
                continue;
            }

            $section = $config['section'];
            $grouped[$section][$resourceId] = [
                'label' => $config['label'],
                'operations' => $operations,
            ];
        }

        return $grouped;
    }
}
