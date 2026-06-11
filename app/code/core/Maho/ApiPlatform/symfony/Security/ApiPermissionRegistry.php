<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;

/**
 * Central registry for API resource permissions.
 *
 * Single source of truth for resource definitions, REST path mappings,
 * and GraphQL field-to-resource resolution. Used by ApiUserVoter (REST),
 * GraphQlPermissionListener (GraphQL), and the admin role editor UI.
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
     *     segmentMap: array<string, string>,
     *     graphQlFieldMap: array<string, string>,
     * }|null
     */
    private static ?array $compiled = null;

    /**
     * Lazy-load the compiled permissions file. Returns an empty registry and logs
     * a warning if the file is missing — the API is then effectively closed (most
     * non-public endpoints deny), which is the safer failure mode than open-by-default.
     *
     * @return array{
     *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
     *     publicRead: list<string>,
     *     customerScoped: array<string, string>,
     *     segmentMap: array<string, string>,
     *     graphQlFieldMap: array<string, string>,
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
                'ApiPermissionRegistry: ' . $path . ' is missing — run `composer dump-autoload`. '
                . 'Falling back to empty registry; API permission checks will deny most requests.',
                \Mage::LOG_WARNING,
            );
            return self::$compiled = [
                'resources' => [],
                'publicRead' => [],
                'customerScoped' => [],
                'segmentMap' => [],
                'graphQlFieldMap' => [],
            ];
        }

        /** @var array{
         *     resources: array<string, array{label: string, section: string, operations: array<string, string>}>,
         *     publicRead: list<string>,
         *     customerScoped: array<string, string>,
         *     segmentMap: array<string, string>,
         *     graphQlFieldMap: array<string, string>,
         * } $data */
        $data = require $path;
        return self::$compiled = $data;
    }

    /**
     * Mutation field name prefixes that map to the 'create' operation.
     * Heuristic — not data — so it stays in the class, not in the attribute.
     */
    private const CREATE_PREFIXES = ['place', 'create', 'register', 'submit', 'subscribe'];

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

    /**
     * Resolve a REST URL path to a resource name.
     *
     * Splits the path into segments and returns the resource mapped by the
     * last known segment. This correctly handles nested paths like
     * /api/rest/v2/orders/5/shipments → 'shipments'.
     */
    public function resolveRestResource(string $path): ?string
    {
        $segmentMap = self::load()['segmentMap'];
        $segments = explode('/', trim($path, '/'));
        $resolved = null;

        foreach ($segments as $segment) {
            // Unmapped segments (e.g. nested 'items') are silently skipped,
            // keeping the parent resource resolution. The compiler omits the
            // legacy `'items' => null` sentinel because the loop already does
            // the right thing.
            if (isset($segmentMap[$segment])) {
                $resolved = $segmentMap[$segment];
            }
        }

        return $resolved;
    }

    /**
     * Check if a resource defines a specific operation
     */
    public function resourceHasOperation(string $resource, string $operation): bool
    {
        return isset(self::load()['resources'][$resource]['operations'][$operation]);
    }

    /**
     * Parse a GraphQL query and return required resource/operation pairs.
     *
     * Handles FieldNode, FragmentSpreadNode, and InlineFragmentNode to
     * prevent permission bypass via fragment queries.
     *
     * @return array<string> List of permissions needed, e.g. ['products/read', 'orders/create']
     */
    public function resolveGraphQlPermissions(string $query): array
    {
        try {
            $document = Parser::parse($query);
        } catch (\Exception) {
            return [];
        }

        $fieldMap = self::load()['graphQlFieldMap'];

        // Build fragment map for resolving FragmentSpreadNode references
        $fragments = [];
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[$definition->name->value] = $definition;
            }
        }

        $permissions = [];

        foreach ($document->definitions as $definition) {
            if (!$definition instanceof OperationDefinitionNode) {
                continue;
            }

            $operationType = $definition->operation ?? 'query';
            $topLevelFields = $this->collectTopLevelFields($definition->selectionSet, $fragments);

            foreach ($topLevelFields as $fieldName) {
                // Skip introspection fields
                if (str_starts_with($fieldName, '__')) {
                    continue;
                }

                $resource = $fieldMap[$fieldName] ?? null;
                if ($resource === null) {
                    continue;
                }

                if ($operationType === 'query') {
                    $permissions[] = $resource . '/read';
                } else {
                    // Mutation — check if it's a 'create' operation
                    $isCreate = false;
                    foreach (self::CREATE_PREFIXES as $prefix) {
                        if (str_starts_with(strtolower($fieldName), $prefix)) {
                            $isCreate = true;
                            break;
                        }
                    }
                    $permissions[] = $resource . '/' . ($isCreate ? 'create' : 'write');
                }
            }
        }

        return array_unique($permissions);
    }

    /**
     * Collect top-level field names from a selection set, resolving fragments.
     *
     * @param array<string, FragmentDefinitionNode> $fragments
     * @return array<string>
     */
    private function collectTopLevelFields(SelectionSetNode $selectionSet, array $fragments): array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $fields[] = $selection->name->value;
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragmentName = $selection->name->value;
                if (isset($fragments[$fragmentName])) {
                    $fields = array_merge(
                        $fields,
                        $this->collectTopLevelFields($fragments[$fragmentName]->selectionSet, $fragments),
                    );
                }
            } elseif ($selection instanceof InlineFragmentNode) {
                $fields = array_merge(
                    $fields,
                    $this->collectTopLevelFields($selection->selectionSet, $fragments),
                );
            }
        }

        return $fields;
    }
}
