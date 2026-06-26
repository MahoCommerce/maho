<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\InflectorInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
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
     * API Platform metadata factories, used to build the GraphQL field→resource
     * index from resolved metadata (see graphQlFieldIndex()). Autowired in the
     * API kernel; null when the class is instantiated with `new` outside the
     * kernel (admin role editor), where only the compiled-file accessors are used.
     */
    public function __construct(
        private readonly ?ResourceNameCollectionFactoryInterface $nameFactory = null,
        private readonly ?ResourceMetadataCollectionFactoryInterface $metadataFactory = null,
        private readonly ?InflectorInterface $inflector = null,
    ) {}

    /**
     * Memoized GraphQL field index. @var array<string, array{resource: string, kind: string, name: string, public: bool}>|null
     */
    private ?array $graphQlIndex = null;

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
     * a warning if the file is missing, the API is then effectively closed (most
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
                'ApiPermissionRegistry: ' . $path . ' is missing, run `composer dump-autoload`. '
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
     * Heuristic, not data, so it stays in the class, not in the attribute.
     */
    private const CREATE_PREFIXES = ['place', 'create', 'register', 'submit', 'subscribe'];

    /**
     * GraphQL mutation-name prefixes that denote a destructive (delete) operation.
     * Mapped to the resource's 'delete' permission — but only when the resource
     * actually defines one (mirroring ApiUserVoter::resolveOperation for REST).
     * Resources without a delete op fall back to 'write', so a mutation is never
     * gated behind a permission that can never be granted.
     */
    private const DELETE_PREFIXES = ['remove', 'delete'];

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
        } catch (\Exception $e) {
            // Never fail open: a query this parser cannot read must not bypass
            // permission analysis just because it returned an empty permission
            // set. The caller (GraphQlPermissionListener) treats a thrown
            // exception as "deny", so an unparseable query is rejected rather
            // than executed unchecked by API Platform's own parser.
            throw new \RuntimeException('Unable to parse GraphQL query for permission analysis.', 0, $e);
        }

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

            $runtimeIndex = $this->graphQlFieldIndex();

            foreach ($topLevelFields as $fieldName) {
                // Skip introspection fields
                if (str_starts_with($fieldName, '__')) {
                    continue;
                }

                // Preferred path: resolve the real schema field name against the
                // runtime index built from API Platform's resolved metadata. This
                // is keyed by the actual exposed field name (e.g. `orders`,
                // `customerOrdersOrders`, `createCouponCoupon`), unlike the
                // compiled graphQlFieldMap which is keyed by operation name and
                // therefore misses most fields. Without this, an unmapped query
                // field fell through to "no permission required" (fail-open),
                // letting any API user read e.g. /orders regardless of grants.
                if (isset($runtimeIndex[$fieldName])) {
                    $info = $runtimeIndex[$fieldName];
                    // Public operations (security: 'true') need no grant, mirroring
                    // REST's isPublicOperation skip, so an API user reaches public
                    // catalog/store reads like anyone else.
                    if ($info['public']) {
                        continue;
                    }
                    if ($info['kind'] === 'read') {
                        $permissions[] = $info['resource'] . '/read';
                        continue;
                    }
                    $permissions[] = $info['resource'] . '/' . $this->classifyMutation($info['name'], $info['resource']);
                    continue;
                }

                // Field absent from the runtime index: only resources declared
                // with the plain ApiPlatform attribute (no Maho permission
                // metadata) reach here — public catalog/reference reads, plus a
                // few auto-generated mutations on read-only resources. A read on
                // them is public so needs no grant; a mutation resolves to a
                // field-name-derived permission no role can hold, so it fails
                // closed. The compiled graphQlFieldMap is deliberately not
                // consulted: it is keyed by operation name, not schema field name,
                // so it never matched a real field here and was the original
                // fail-open's root cause (the runtime index above replaces it).
                if ($operationType === 'query') {
                    continue;
                }
                $fieldLower = strtolower($fieldName);
                $op = array_any(self::CREATE_PREFIXES, fn($prefix) => str_starts_with($fieldLower, $prefix))
                    ? 'create'
                    : 'write';
                $permissions[] = $fieldName . '/' . $op;
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
    private function collectTopLevelFields(SelectionSetNode $selectionSet, array $fragments, array $visited = []): array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $fields[] = $selection->name->value;
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragmentName = $selection->name->value;
                // Guard against cyclic fragment spreads (A → B → A). Parser::parse()
                // accepts them syntactically — cycle detection only runs during full
                // GraphQL validation, which happens after this permission check — so
                // without this guard a crafted query would recurse until the stack
                // is exhausted.
                if (isset($fragments[$fragmentName]) && !isset($visited[$fragmentName])) {
                    $fields = array_merge(
                        $fields,
                        $this->collectTopLevelFields(
                            $fragments[$fragmentName]->selectionSet,
                            $fragments,
                            $visited + [$fragmentName => true],
                        ),
                    );
                }
            } elseif ($selection instanceof InlineFragmentNode) {
                $fields = array_merge(
                    $fields,
                    $this->collectTopLevelFields($selection->selectionSet, $fragments, $visited),
                );
            }
        }

        return $fields;
    }

    /**
     * Build (and memoize) a map of GraphQL schema field name → resource metadata,
     * derived from API Platform's RESOLVED resource metadata.
     *
     * The compiled `graphQlFieldMap` is keyed by the operation `name` (e.g.
     * `productBySku`, `customerOrders`), but API Platform exposes those operations
     * under field names suffixed with the (pluralized) short name (e.g.
     * `productBySkuProduct`, `customerOrdersOrders`) and also auto-generates
     * default operations the compiler never sees (e.g. `orders`, `createMedia`).
     * Resolving against the live metadata closes both gaps so every GraphQL
     * operation an API user can invoke maps back to its resource + permission.
     *
     * Returns an empty index outside the API kernel (factories not injected),
     * where this method is never called.
     *
     * @return array<string, array{resource: string, kind: string, name: string, public: bool}>
     */
    private function graphQlFieldIndex(): array
    {
        if ($this->graphQlIndex !== null) {
            return $this->graphQlIndex;
        }
        if ($this->nameFactory === null || $this->metadataFactory === null || $this->inflector === null) {
            return $this->graphQlIndex = [];
        }

        // API Platform's own inflector instance (the one FieldsBuilder uses), so
        // pluralisation matches the generated schema field names exactly.
        $inflector = $this->inflector;
        $index = [];

        foreach ($this->nameFactory->create() as $resourceClass) {
            $resourceId = $this->resolveResourceId($resourceClass);
            if ($resourceId === null) {
                continue;
            }

            foreach ($this->metadataFactory->create($resourceClass) as $metadata) {
                $shortName = $metadata->getShortName();
                if (!is_string($shortName) || $shortName === '') {
                    continue;
                }

                foreach ($metadata->getGraphQlOperations() ?? [] as $operation) {
                    $name = $operation->getName();
                    if (!is_string($name) || $name === '') {
                        continue;
                    }

                    // Replicate ApiPlatform\GraphQl\Type\FieldsBuilder field naming
                    // (FieldsBuilder.php:88/110/136), the runtime listener matches
                    // against these exact field names.
                    if ($operation instanceof QueryCollection) {
                        $base = $name === 'collection_query' ? $shortName : $name . $shortName;
                        $field = $inflector->pluralize(lcfirst($base));
                        $kind = 'read';
                    } elseif ($operation instanceof Query) {
                        $field = $name === 'item_query' ? lcfirst($shortName) : lcfirst($name . $shortName);
                        $kind = 'read';
                    } else {
                        // Mutation / DeleteMutation. Internal snake_case identifiers
                        // (e.g. add_cart_item) are not exposed as schema fields.
                        if (str_contains($name, '_')) {
                            continue;
                        }
                        $field = $name . $shortName;
                        $kind = 'mutation';
                    }

                    $security = $operation->getSecurity();
                    $public = is_string($security) && trim($security, '" ') === 'true';

                    // First definition wins, mirrors the compiler's "canonical
                    // operation registered before aliases" precedence.
                    $index[$field] ??= [
                        'resource' => $resourceId,
                        'kind' => $kind,
                        'name' => $name,
                        'public' => $public,
                    ];
                }
            }
        }

        return $this->graphQlIndex = $index;
    }

    /**
     * Resolve a resource class to its Maho permission id (mahoId), reading the
     * `#[Maho\Config\ApiResource]` attribute argument directly to avoid running
     * the heavy parent constructor. Falls back to the same short-name derivation
     * the compiler uses (deriveIdFromShortName) when mahoId isn't set, and
     * returns null for resources that carry no Maho attribute at all.
     */
    private function resolveResourceId(string $resourceClass): ?string
    {
        try {
            $reflection = new \ReflectionClass($resourceClass);
        } catch (\ReflectionException) {
            return null;
        }

        $attributes = $reflection->getAttributes(\Maho\Config\ApiResource::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ($attributes === []) {
            return null;
        }

        foreach ($attributes as $attribute) {
            $args = $attribute->getArguments();
            if (isset($args['mahoId']) && is_string($args['mahoId']) && $args['mahoId'] !== '') {
                return $args['mahoId'];
            }
            $shortName = $args['shortName'] ?? null;
            if (is_string($shortName) && $shortName !== '') {
                return $this->deriveResourceId($shortName);
            }
        }

        return $this->deriveResourceId($reflection->getShortName());
    }

    /**
     * Mirror of ApiPermissionCompiler::deriveIdFromShortName so a resource whose
     * mahoId is auto-derived resolves to the same id the compiled registry uses.
     */
    private function deriveResourceId(string $shortName): ?string
    {
        if ($shortName === '') {
            return null;
        }
        $kebab = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '-$1', $shortName));
        if (str_ends_with($kebab, 'y') && preg_match('/[aeiou]y$/', $kebab) === 0) {
            return substr($kebab, 0, -1) . 'ies';
        }
        if (preg_match('/(s|x|z|ch|sh)$/', $kebab) === 1) {
            return $kebab . 'es';
        }
        if (str_ends_with($kebab, 's')) {
            return $kebab;
        }
        return $kebab . 's';
    }

    /**
     * Classify a mutation operation name into a create/write/delete permission
     * verb, mirroring the heuristic used for the compiled-map fallback path:
     * delete is only used when the resource actually defines a delete operation,
     * so a mutation is never gated behind an ungrantable permission.
     */
    private function classifyMutation(string $name, string $resource): string
    {
        $lower = strtolower($name);
        // create/delete are only used when the resource actually defines that
        // operation (mirrors ApiUserVoter::resolveOperation for REST); otherwise
        // fall back to the catch-all write permission so a mutation is never gated
        // behind a permission that can never be granted.
        if (array_any(self::CREATE_PREFIXES, fn($prefix) => str_starts_with($lower, $prefix))
            && $this->resourceHasOperation($resource, 'create')
        ) {
            return 'create';
        }
        if (array_any(self::DELETE_PREFIXES, fn($prefix) => str_starts_with($lower, $prefix))
            && $this->resourceHasOperation($resource, 'delete')
        ) {
            return 'delete';
        }
        return 'write';
    }
}
