<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class Maho_Intelligence_Model_Mcp_Server
{
    private const PROTOCOL_VERSION = '2024-11-05';
    private const JSONRPC_VERSION = '2.0';

    private LoopInterface $loop;
    private Maho_Intelligence_Model_Mcp_Transport $transport;
    private Maho_Intelligence_Model_Registry $registry;
    /** @var array<string, array{description: string, inputSchema: array, handler: callable}> */
    private array $tools = [];

    public function run(): void
    {
        $this->loop = Loop::get();
        $this->registry = Mage::getModel('intelligence/registry');
        $this->transport = new Maho_Intelligence_Model_Mcp_Transport($this->loop);

        $this->registerTools();

        $this->transport->listen(
            onMessage: fn(array $msg) => $this->handleMessage($msg),
            onClose: fn() => $this->loop->stop(),
        );

        $this->loop->run();
    }

    private function registerTools(): void
    {
        $registry = $this->registry;

        $this->addTool(
            'resolve_alias',
            'Resolve a Maho class alias (e.g. catalog/product) to its PHP class name, file path, and rewrite info. Type must be one of: model, block, helper, resource_model.',
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'description' => 'Alias type: model, block, helper, or resource_model'],
                    'alias' => ['type' => 'string', 'description' => 'Class alias (e.g. catalog/product)'],
                ],
                'required' => ['type', 'alias'],
            ],
            fn(array $args) => $registry->get('classAlias', 'resolveAlias', [$args['type'], $args['alias']]),
        );

        $this->addTool(
            'list_aliases',
            'List all class aliases for a given type. Type must be one of: model, block, helper, resource_model.',
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'description' => 'Alias type: model, block, helper, or resource_model'],
                ],
                'required' => ['type'],
            ],
            fn(array $args) => $registry->get('classAlias', 'getAllAliases', [$args['type']]),
        );

        $this->addTool(
            'list_rewrites',
            'List all class rewrites with conflict detection. Shows original class, rewriting class, and flags conflicts where multiple modules rewrite the same class.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('classAlias', 'getAllRewrites'),
        );

        $this->addTool(
            'list_events',
            'List all events and their observers. Optionally filter by event name pattern (substring match).',
            [
                'type' => 'object',
                'properties' => [
                    'pattern' => ['type' => 'string', 'description' => 'Optional substring to filter event names'],
                ],
            ],
            fn(array $args) => $this->filterEvents($args['pattern'] ?? null),
        );

        $this->addTool(
            'get_merged_config',
            'Get the merged XML configuration for a given path (e.g. global/models, frontend/routers, default/web/secure). Returns the fully merged config from all modules as a JSON object.',
            [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Config path (e.g. global/models)'],
                ],
                'required' => ['path'],
            ],
            fn(array $args) => $this->getMergedConfig($args['path']),
        );

        $this->addTool(
            'list_modules',
            'List all active modules with their versions, code pool, and dependencies.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('module', 'getAllModules'),
        );

        $this->addTool(
            'list_config_paths',
            'List all system.xml configuration paths with labels, types, and default values. Optionally filter by section name.',
            [
                'type' => 'object',
                'properties' => [
                    'section' => ['type' => 'string', 'description' => 'Optional section name to filter by'],
                ],
            ],
            fn(array $args) => $this->filterConfigPaths($args['section'] ?? null),
        );

        $this->addTool(
            'list_layout_handles',
            'List layout handles and their block hierarchy for an area. Area must be frontend or adminhtml.',
            [
                'type' => 'object',
                'properties' => [
                    'area' => ['type' => 'string', 'description' => 'Area: frontend or adminhtml', 'default' => 'frontend'],
                ],
            ],
            fn(array $args) => $registry->get('layout', 'getHandles', [$args['area'] ?? 'frontend']),
        );

        $this->addTool(
            'list_cron_jobs',
            'List all cron job definitions with their model::method callbacks and schedules.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('cron', 'getAllJobs'),
        );

        $this->addTool(
            'list_tables',
            'List all database table name mappings from resource model configuration.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('table', 'getAllTables'),
        );

        $this->addTool(
            'list_acl_resources',
            'Get the ACL resource tree for admin permissions.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('acl', 'getTree'),
        );

        $this->addTool(
            'list_admin_menu',
            'Get the admin panel menu structure with titles, actions, and sort order.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('adminMenu', 'getMenu'),
        );

        $this->addTool(
            'list_routes',
            'List all frontend and admin route definitions with frontNames, modules, and controller overrides.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('router', 'getAllRoutes'),
        );

        $this->addTool(
            'list_eav_entity_types',
            'List all EAV entity types (product, category, customer, etc.) with their models and tables.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('eavAttribute', 'getEntityTypes'),
        );

        $this->addTool(
            'list_eav_attributes',
            'List all EAV attributes for a given entity type (e.g. catalog_product, customer, catalog_category) with backend types, frontend inputs, and validation info.',
            [
                'type' => 'object',
                'properties' => [
                    'entity_type' => ['type' => 'string', 'description' => 'Entity type code (e.g. catalog_product, customer, catalog_category, customer_address)'],
                ],
                'required' => ['entity_type'],
            ],
            fn(array $args) => $registry->get('eavAttribute', 'getAttributes', [$args['entity_type']]),
        );

        $this->addTool(
            'list_stores',
            'Get the website → store group → store view hierarchy showing multi-store configuration.',
            ['type' => 'object', 'properties' => new \stdClass()],
            fn(array $args) => $registry->get('store', 'getHierarchy'),
        );

        $this->addTool(
            'get_db_schema',
            'Get full database table schema including columns (types, nullable, defaults), indexes, and foreign keys. Accepts a table name (e.g. catalog_product_entity) or resource alias (e.g. catalog/product_entity).',
            [
                'type' => 'object',
                'properties' => [
                    'table' => ['type' => 'string', 'description' => 'Table name or resource alias (e.g. catalog_product_entity or catalog/product_entity)'],
                ],
                'required' => ['table'],
            ],
            fn(array $args) => $registry->get('dbSchema', 'getTableSchema', [$args['table']]),
        );

        $this->addTool(
            'get_class_context',
            'Get full context for a Maho class alias: resolved class, file, rewrites, class hierarchy, module, observed events, and all rewrites in the same group. Type must be one of: model, block, helper, resource_model.',
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'description' => 'Alias type: model, block, helper, or resource_model'],
                    'alias' => ['type' => 'string', 'description' => 'Class alias (e.g. catalog/product)'],
                ],
                'required' => ['type', 'alias'],
            ],
            fn(array $args) => $registry->get('classContext', 'getContext', [$args['type'], $args['alias']]),
        );

        $this->addTool(
            'get_template_overrides',
            'Get the theme fallback chain for a template file, showing all locations where it could exist and which one is resolved. Also lists all theme-overridden templates when called without a template path.',
            [
                'type' => 'object',
                'properties' => [
                    'template' => ['type' => 'string', 'description' => 'Template path (e.g. catalog/product/view.phtml). Omit to list all overridden templates.'],
                    'area' => ['type' => 'string', 'description' => 'Area: frontend or adminhtml (default: frontend)'],
                ],
            ],
            fn(array $args) => isset($args['template'])
                ? $registry->get('templateFallback', 'getOverrides', [$args['template'], $args['area'] ?? 'frontend'])
                : $registry->get('templateFallback', 'getThemeOverrides', [$args['area'] ?? 'frontend']),
        );
    }

    private function addTool(string $name, string $description, array $inputSchema, callable $handler): void
    {
        $this->tools[$name] = [
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler' => $handler,
        ];
    }

    private function handleMessage(array $message): void
    {
        $method = $message['method'] ?? null;
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? [];

        // Notifications (no id) don't require a response
        if ($id === null) {
            return;
        }

        [$found, $result] = match ($method) {
            'initialize' => [true, $this->handleInitialize()],
            'ping' => [true, new \stdClass()],
            'tools/list' => [true, $this->handleToolsList()],
            'tools/call' => [true, $this->handleToolsCall($params)],
            default => [false, null],
        };

        if (!$found) {
            $this->sendError($id, -32601, "Method not found: {$method}");
        } else {
            $this->sendResult($id, $result);
        }
    }

    private function handleInitialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'maho-intelligence',
                'version' => Mage::getVersion(),
            ],
            'instructions' => $this->getInstructions(),
        ];
    }

    private function handleToolsList(): array
    {
        $tools = [];
        foreach ($this->tools as $name => $tool) {
            $tools[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }
        return ['tools' => $tools];
    }

    private function handleToolsCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!isset($this->tools[$name])) {
            return [
                'content' => [['type' => 'text', 'text' => "Unknown tool: {$name}"]],
                'isError' => true,
            ];
        }

        try {
            $result = ($this->tools[$name]['handler'])($arguments);
            $text = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return [
                'content' => [['type' => 'text', 'text' => $text]],
            ];
        } catch (\Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => "Error: {$e->getMessage()}"]],
                'isError' => true,
            ];
        }
    }

    private function sendResult(int|string $id, mixed $result): void
    {
        $this->transport->send([
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function sendError(int|string $id, int $code, string $message): void
    {
        $this->transport->send([
            'jsonrpc' => self::JSONRPC_VERSION,
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    private function getInstructions(): string
    {
        return <<<'INSTRUCTIONS'
Maho Intelligence MCP Server - provides deep insight into the Maho ecommerce platform's runtime configuration.

Maho uses a class alias system where Mage::getModel('catalog/product') resolves to Mage_Catalog_Model_Product via merged XML config. This server exposes that merged configuration so you can understand how the application is wired.

Key concepts:
- Class aliases: 'group/class' strings that resolve to PHP class names (e.g. 'catalog/product' -> Mage_Catalog_Model_Product)
- Types: model, block, helper, resource_model - different class registries in the config
- Rewrites: modules can override default class resolution (similar to dependency injection overrides)
- Events: observer pattern where modules register listeners for named events
- Config paths: system.xml defines admin-configurable settings at paths like 'web/secure/base_url'
- Layout handles: XML-defined UI composition with blocks and templates
- Routers: URL frontName-to-module mappings with controller override chains
- EAV: Entity-Attribute-Value system for extensible product/customer/category attributes
- Store hierarchy: website -> store group -> store view for multi-store setups
- ACL: admin permission tree controlling access to backend features
- Admin menu: navigation structure for the admin panel
INSTRUCTIONS;
    }

    private function filterEvents(?string $pattern): array
    {
        $events = $this->registry->get('event', 'getAllEvents');
        if ($pattern === null) {
            return $events;
        }

        $pattern = strtolower($pattern);
        $filtered = [];
        foreach ($events as $area => $areaEvents) {
            foreach ($areaEvents as $name => $event) {
                if (str_contains($name, $pattern)) {
                    $filtered[$area][$name] = $event;
                }
            }
        }
        return $filtered;
    }

    private function filterConfigPaths(?string $section): array
    {
        $paths = $this->registry->get('configPath', 'getAllPaths');
        if ($section === null) {
            return $paths;
        }

        return array_filter($paths, fn($info) => str_starts_with($info['path'], $section . '/'));
    }

    private function getMergedConfig(string $path): array|string|null
    {
        $node = Mage::getConfig()->getNode($path);
        if ($node === false) {
            return null;
        }

        if ($node->hasChildren()) {
            return $node->asArray();
        }

        return (string) $node;
    }
}
