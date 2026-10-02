<?php

/**
 * Local tool that opens an admin page in the administrator's browser after the answer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Maho\Routing\RouteCollectionBuilder;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Not an MCP tool: it has no REST twin. The panel receives a `navigate` event when it
 * runs and changes the browser location once the turn is complete.
 *
 * The pages come from the admin menu, filtered by the role of the current administrator,
 * so a module that adds a menu entry is reachable without any change here. A record page
 * is the `edit` or `view` action of the controller behind the menu entry, and the name of
 * its id parameter is read from that action's source.
 */
final class AdminPageTool
{
    public const NAME = 'admin_open_page';

    /** @var array<string, array{title: string, action: string}>|null */
    private ?array $pages = null;

    public function tool(): Tool
    {
        $pages = $this->pages();
        $lines = [];
        foreach ($pages as $path => $page) {
            $lines[] = sprintf('%s = %s', $path, $page['title']);
        }

        return new Tool(
            new ExecutionReference(self::class, 'open'),
            self::NAME,
            'Open an admin page in the administrator\'s browser after you answer. Use it when the administrator asks to go somewhere, or wants to edit a record by hand. Pages (menu path = title): ' . implode('; ', $lines) . '.',
            [
                'type' => 'object',
                'properties' => [
                    'page' => [
                        'type' => 'string',
                        'description' => 'The menu path of the page.',
                        'enum' => array_keys($pages),
                    ],
                    'record_id' => [
                        'type' => 'string',
                        'description' => 'Id of one record. Opens the edit or view page of that record instead of the list.',
                    ],
                    'params' => [
                        'type' => 'object',
                        'description' => 'Extra URL parameters, for example {"section": "general"} for the configuration page.',
                        'additionalProperties' => ['type' => 'string'],
                    ],
                ],
                'required' => ['page'],
                'additionalProperties' => false,
            ],
            ['title' => 'Open admin page', 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string, url?: string}
     */
    public function open(array $arguments): array
    {
        $pages = $this->pages();
        $path = (string) ($arguments['page'] ?? '');
        $page = $pages[$path] ?? null;
        if ($page === null) {
            return ['ok' => false, 'text' => sprintf('Unknown page "%s". Allowed pages: %s.', $path, implode(', ', array_keys($pages)))];
        }

        $params = [];
        foreach ((array) ($arguments['params'] ?? []) as $key => $value) {
            if (is_scalar($value) && preg_match('/^[a-z][a-z0-9_]*$/', (string) $key)) {
                $params[(string) $key] = $this->cleanValue((string) $value);
            }
        }

        $route = $page['action'];
        $recordId = $arguments['record_id'] ?? null;
        if (is_scalar($recordId) && trim((string) $recordId) !== '') {
            $record = $this->recordRoute($page['action']);
            if ($record === null) {
                return ['ok' => false, 'text' => sprintf('The page "%s" has no record page. Open it without record_id.', $path)];
            }
            [$route, $idParam] = $record;
            $params[$idParam] = $this->cleanValue((string) $recordId);
        }

        $url = \Mage::helper('adminhtml')->getUrl($route, $params);

        return ['ok' => true, 'text' => sprintf('The browser opens %s when you finish your answer. Tell the administrator in one sentence.', $url), 'url' => $url];
    }

    /**
     * The admin menu entries the current administrator can open, keyed by menu path.
     *
     * @return array<string, array{title: string, action: string}>
     */
    public function pages(): array
    {
        if ($this->pages === null) {
            $this->pages = [];
            $menu = \Mage::getSingleton('admin/config')->getAdminhtmlConfig()->getNode('menu');
            if ($menu instanceof \Maho\Simplexml\Element) {
                $this->collect($menu, '', []);
            }
        }

        return $this->pages;
    }

    /** @param list<string> $titles */
    private function collect(\Maho\Simplexml\Element $parent, string $path, array $titles): void
    {
        $session = \Mage::getSingleton('admin/session');
        foreach ($parent->children() as $name => $child) {
            if ((string) $child->disabled === '1') {
                continue;
            }
            $resource = 'admin/' . ($child->resource ? (string) $child->resource : $path . $name);
            if (!$session->isAllowed($resource) || !$this->moduleOutputEnabled($child) || !$this->dependsMet($child)) {
                continue;
            }

            $module = (string) ($child->attributes()['module'] ?? 'adminhtml');
            $childTitles = [...$titles, (string) \Mage::helper($module)->__((string) $child->title)];
            $action = trim((string) $child->action, '/');
            if ($action !== '') {
                $this->pages[$path . $name] = ['title' => implode(' > ', $childTitles), 'action' => $action];
            }
            if ($child->children) {
                $this->collect($child->children, $path . $name . '/', $childTitles);
            }
        }
    }

    private function moduleOutputEnabled(\Maho\Simplexml\Element $child): bool
    {
        $module = (string) ($child->attributes()['module'] ?? '');

        return \Mage::helper($module === '' ? 'adminhtml' : $module)->isModuleOutputEnabled();
    }

    private function dependsMet(\Maho\Simplexml\Element $child): bool
    {
        if (!$child->depends) {
            return true;
        }
        foreach ($child->depends->module ?? [] as $module) {
            $node = \Mage::getConfig()->getNode('modules/' . $module);
            if (!$node || !$node->is('active')) {
                return false;
            }
        }
        foreach ($child->depends->config ?? [] as $configPath) {
            if (!\Mage::getStoreConfigFlag((string) $configPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The edit or view action of the controller behind a menu action, with its id parameter.
     *
     * @return array{string, string}|null route and id parameter name
     */
    private function recordRoute(string $menuAction): ?array
    {
        $parts = explode('/', $menuAction);
        $routeName = $parts[0];
        $controller = $parts[1] ?? 'index';
        $frontName = RouteCollectionBuilder::getFrontNameByRoute($routeName) ?? $routeName;

        foreach (['edit', 'view'] as $action) {
            $route = RouteCollectionBuilder::resolveRoute($frontName, $controller, $action);
            if ($route !== null) {
                return [$routeName . '/' . $controller . '/' . $action, $this->idParameter($route['class'], $action . 'Action')];
            }
        }

        return null;
    }

    /**
     * The request parameter the action reads the record id from: the first `getParam('…id')`
     * in its body, or in an `_init…()` method the body calls. `id` when neither names one.
     */
    private function idParameter(string $class, string $method): string
    {
        if (!class_exists($class) || !method_exists($class, $method)) {
            return 'id';
        }
        $body = $this->methodBody(new \ReflectionMethod($class, $method));
        if (preg_match('/getParam\(\'([a-z_]*id)\'\)/', $body, $match)) {
            return $match[1];
        }
        if (preg_match_all('/\$this->(_init\w*)\(/', $body, $calls)) {
            foreach (array_unique($calls[1]) as $init) {
                if (method_exists($class, $init) && preg_match('/getParam\(\'([a-z_]*id)\'\)/', $this->methodBody(new \ReflectionMethod($class, $init)), $match)) {
                    return $match[1];
                }
            }
        }

        return 'id';
    }

    private function methodBody(\ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        if ($file === false || $start === false || $end === false) {
            return '';
        }
        $lines = file($file);

        return $lines === false ? '' : implode('', array_slice($lines, $start - 1, $end - $start + 1));
    }

    private function cleanValue(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_\-.]/', '', $value);
    }
}
