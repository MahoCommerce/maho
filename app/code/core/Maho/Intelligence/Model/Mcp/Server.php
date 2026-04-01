<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;

class Maho_Intelligence_Model_Mcp_Server
{
    public function run(): void
    {
        $registry = Mage::getModel('intelligence/registry');

        $server = Server::make()
            ->withServerInfo('maho-intelligence', Mage::getVersion())
            ->withInstructions($this->getInstructions())
            ->withTool(
                fn (string $type, string $alias) => $registry->get('classAlias', 'resolveAlias', [$type, $alias]),
                name: 'resolve_alias',
                description: 'Resolve a Maho class alias (e.g. catalog/product) to its PHP class name, file path, and rewrite info. Type must be one of: model, block, helper, resource_model.',
            )
            ->withTool(
                fn (string $type) => $registry->get('classAlias', 'getAllAliases', [$type]),
                name: 'list_aliases',
                description: 'List all class aliases for a given type. Type must be one of: model, block, helper, resource_model.',
            )
            ->withTool(
                fn () => $registry->get('classAlias', 'getAllRewrites'),
                name: 'list_rewrites',
                description: 'List all class rewrites with conflict detection. Shows original class, rewriting class, and flags conflicts where multiple modules rewrite the same class.',
            )
            ->withTool(
                fn (?string $pattern = null) => $this->filterEvents($registry, $pattern),
                name: 'list_events',
                description: 'List all events and their observers. Optionally filter by event name pattern (substring match).',
            )
            ->withTool(
                fn (string $path) => $this->getMergedConfig($path),
                name: 'get_merged_config',
                description: 'Get the merged XML configuration for a given path (e.g. global/models, frontend/routers, default/web/secure). Returns the fully merged config from all modules as a JSON object.',
            )
            ->withTool(
                fn () => $registry->get('module', 'getAllModules'),
                name: 'list_modules',
                description: 'List all active modules with their versions, code pool, and dependencies.',
            )
            ->withTool(
                fn (?string $section = null) => $this->filterConfigPaths($registry, $section),
                name: 'list_config_paths',
                description: 'List all system.xml configuration paths with labels, types, and default values. Optionally filter by section name.',
            )
            ->withTool(
                fn (string $area = 'frontend') => $registry->get('layout', 'getHandles', [$area]),
                name: 'list_layout_handles',
                description: 'List layout handles and their block hierarchy for an area. Area must be frontend or adminhtml.',
            )
            ->withTool(
                fn () => $registry->get('cron', 'getAllJobs'),
                name: 'list_cron_jobs',
                description: 'List all cron job definitions with their model::method callbacks and schedules.',
            )
            ->withTool(
                fn () => $registry->get('table', 'getAllTables'),
                name: 'list_tables',
                description: 'List all database table name mappings from resource model configuration.',
            )
            ->withTool(
                fn () => $registry->get('acl', 'getTree'),
                name: 'list_acl_resources',
                description: 'Get the ACL resource tree for admin permissions.',
            )
            ->build();

        $server->listen(new StdioServerTransport());
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
INSTRUCTIONS;
    }

    private function filterEvents(Maho_Intelligence_Model_Registry $registry, ?string $pattern): array
    {
        $events = $registry->get('event', 'getAllEvents');
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

    private function filterConfigPaths(Maho_Intelligence_Model_Registry $registry, ?string $section): array
    {
        $paths = $registry->get('configPath', 'getAllPaths');
        if ($section === null) {
            return $paths;
        }

        return array_filter($paths, fn ($info) => str_starts_with($info['path'], $section . '/'));
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
