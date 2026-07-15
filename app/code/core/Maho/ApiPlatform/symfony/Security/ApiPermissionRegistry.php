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
 * Run `composer dump-autoload` after adding/modifying an attribute.
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
     * Lazy-load the compiled permissions file. Returns an empty registry and logs
     * a warning if the file is missing; the role editor then simply shows no
     * grantable permissions, which is the safe failure mode.
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

        $path = (defined('BP') ? BP : dirname(__DIR__, 7)) . self::COMPILED_FILE;

        if (!is_file($path)) {
            \Mage::log(
                'ApiPermissionRegistry: ' . $path . ' is missing, run `composer dump-autoload`. '
                . 'Falling back to empty registry; the role editor will show no grantable permissions.',
                \Mage::LOG_WARNING,
            );
            return self::$compiled = [
                'resources' => [],
                'publicRead' => [],
                'customerScoped' => [],
            ];
        }

        /** @var array{
         *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
         *     publicRead: list<string>,
         *     customerScoped: array<string, string>,
         * } $data */
        $data = require $path;
        return self::$compiled = $data;
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
