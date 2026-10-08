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

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
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
    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {}

    public const NAME = 'admin_open_page';
    public const FILL_NAME = 'admin_fill_form';
    public const ACTION_NAME = 'admin_page_action';
    public const NAMES = [self::NAME, self::FILL_NAME, self::ACTION_NAME];
    public const ACTIONS = ['click', 'open_tab', 'set_field', 'open_row'];

    public static function title(string $name): string
    {
        return match ($name) {
            self::FILL_NAME => 'Fill admin form',
            self::ACTION_NAME => 'Act on the page',
            default => 'Open admin page',
        };
    }

    /**
     * The page action tool: one click, tab switch, field change or grid row on the page the
     * administrator has open, performed by the panel after the answer. The panel resolves
     * the target against the visible elements only, the ones the screen digest listed.
     */
    public function actionTool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'act'),
            self::ACTION_NAME,
            'Act on the admin page the administrator has open, after you answer: open a tab by its name, set a form field by its label or name, click a button by its label (for example "Save Page" when the administrator says "save"), or open a grid row by its number in the row list (target "3" for "the third one"). Use the buttons, tabs, fields and rows listed under what the administrator sees; never guess a label. Up to three steps in order, and a click or an open_row must be the last step because the page may reload. The next message shows you the result.',
            [
                'type' => 'object',
                'properties' => [
                    'steps' => [
                        'type' => 'array',
                        'description' => 'The steps, in order. Example: set the Comment field, then click Submit Comment.',
                        'minItems' => 1,
                        'maxItems' => 3,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'action' => ['type' => 'string', 'description' => 'What to do.', 'enum' => self::ACTIONS],
                                'target' => ['type' => 'string', 'description' => 'The button label, the tab name, or the field label or name, as shown on the page. For open_row, the row number.'],
                                'value' => ['type' => 'string', 'description' => 'The value to set, for set_field only.'],
                            ],
                            'required' => ['action', 'target'],
                        ],
                    ],
                ],
                'required' => ['steps'],
                'additionalProperties' => false,
            ],
            ['title' => self::title(self::ACTION_NAME), 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string, steps?: list<array{action: string, target: string, value: string|null}>}
     */
    public function act(array $arguments): array
    {
        $raw = $arguments['steps'] ?? null;
        if (!is_array($raw) || $raw === [] || count($raw) > 3) {
            return ['ok' => false, 'text' => 'Pass one to three steps, each with an action (' . implode(', ', self::ACTIONS) . ') and a target.'];
        }
        $steps = [];
        foreach (array_values($raw) as $i => $step) {
            $action = is_array($step) ? (string) ($step['action'] ?? '') : '';
            $target = is_array($step) ? trim((string) ($step['target'] ?? '')) : '';
            $value = is_array($step) ? ($step['value'] ?? null) : null;
            if (!in_array($action, self::ACTIONS, true) || $target === '') {
                return ['ok' => false, 'text' => sprintf('Step %d needs an action (%s) and a target.', $i + 1, implode(', ', self::ACTIONS))];
            }
            if ($action === 'set_field' && !is_scalar($value)) {
                return ['ok' => false, 'text' => sprintf('Step %d: set_field needs a value.', $i + 1)];
            }
            if ($action === 'open_row' && !preg_match('/^[1-9]\d*$/', $target)) {
                return ['ok' => false, 'text' => sprintf('Step %d: open_row needs the row number as its target, for example "3".', $i + 1)];
            }
            if (in_array($action, ['click', 'open_row'], true) && $i !== count($raw) - 1) {
                return ['ok' => false, 'text' => sprintf('Step %d: a click or an open_row must be the last step, because the page may reload.', $i + 1)];
            }
            $steps[] = ['action' => $action, 'target' => mb_substr($target, 0, 200), 'value' => is_scalar($value) ? (string) $value : null];
        }
        $summary = implode(', then ', array_map(static fn(array $s): string => sprintf('%s "%s"', str_replace('_', ' ', $s['action']), $s['target']), $steps));

        return [
            'ok' => true,
            'text' => sprintf('The panel performs this when you finish your answer: %s. The page may reload; the next message shows its new state. Tell the administrator in one sentence what happens.', $summary),
            'steps' => $steps,
        ];
    }

    /** @var array<string, array{title: string, action: string, acl: string}>|null */
    private ?array $pages = null;

    public function tool(): Tool
    {
        $pages = $this->pages();
        $lines = [];
        foreach ($pages as $path => $page) {
            $lines[] = sprintf('%s = %s', $path, $page['title']);
        }

        return ToolDefinition::create(
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
                        'description' => 'Extra URL parameters the page understands, for example {"section": "general"} for the configuration page, or {"store": "3"} for a page with a store view switcher (products, categories, configuration). A CMS page or block has no store parameter: each store view has its own record, so open that record.',
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
     * The form variant: the browser opens the edit form, or the new-record form, with
     * the given values filled in. The administrator reviews the form and saves it.
     */
    public function fillTool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'fill'),
            self::FILL_NAME,
            'Open an admin edit form in the administrator\'s browser with values filled in, so the administrator reviews and saves it. Prefer it over an update tool for a long text such as page content, a description or an email template, and for a record the administrator wants to adjust by hand. Use the same menu paths as admin_open_page. Without record_id it opens the form for a new record. The fields are the API field names of the resource.',
            [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => 'string', 'description' => 'The menu path of the page, as in admin_open_page.', 'enum' => array_keys($this->pages())],
                    'record_id' => ['type' => 'string', 'description' => 'Id of the record to edit. Omit it to open the form for a new record.'],
                    'fields' => ['type' => 'object', 'description' => 'Field values to fill in, keyed by API field name, for example {"title": "..."}. A value replaces the field. To add to a long field without resending it, pass an object: {"content": {"prepend": "<p>...</p>"}} puts the text before the current value, {"append": "..."} after it. Use prepend or append whenever you did not read the whole current value.', 'additionalProperties' => true],
                    'params' => ['type' => 'object', 'description' => 'Extra URL parameters the page understands, for example {"store": "3"} for a page with a store view switcher (products, categories, configuration). A CMS page or block has no store parameter: each store view has its own record, so open that record.', 'additionalProperties' => ['type' => 'string']],
                ],
                'required' => ['page', 'fields'],
                'additionalProperties' => false,
            ],
            ['title' => self::title(self::FILL_NAME), 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string, url?: string, fields?: array<string, mixed>}
     */
    public function fill(array $arguments): array
    {
        $fields = $arguments['fields'] ?? null;
        if (!is_array($fields) || $fields === []) {
            return ['ok' => false, 'text' => 'Pass at least one field value in "fields".'];
        }
        $clean = [];
        foreach ($fields as $name => $value) {
            if (!is_string($name) || preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) !== 1) {
                continue;
            }
            if (is_array($value) && array_key_exists('prepend', $value) === false && array_key_exists('append', $value) === false && !array_is_list($value)) {
                continue;
            }
            if (is_array($value) && !array_is_list($value)) {
                $value = array_intersect_key($value, ['prepend' => true, 'append' => true]);
                $value = array_filter($value, is_scalar(...));
                if ($value === []) {
                    continue;
                }
            }
            if (is_scalar($value) || is_array($value) || $value === null) {
                $clean[$name] = $value;
            }
        }
        if ($clean === []) {
            return ['ok' => false, 'text' => 'No usable field: a field name is letters, digits and underscores.'];
        }

        $outcome = $this->open($arguments, true);
        if (!$outcome['ok']) {
            return $outcome;
        }

        return [
            'ok' => true,
            'text' => sprintf('The browser opens the form at %s with these fields filled in: %s. The administrator reviews the form and saves it. Do not call an update or create tool for the same change. Tell the administrator in one sentence what to check.', $outcome['url'], implode(', ', array_keys($clean))),
            'url' => $outcome['url'],
            'fields' => $clean,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string, url?: string}
     */
    public function open(array $arguments, bool $newRecordWithoutId = false): array
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
        } elseif ($newRecordWithoutId) {
            $route = $this->actionRoute($page['action'], 'new');
            if ($route === null) {
                return ['ok' => false, 'text' => sprintf('The page "%s" has no form for a new record.', $path)];
            }
        }

        $url = \Mage::helper('adminhtml')->getUrl($route, $params);

        return ['ok' => true, 'text' => sprintf('The browser opens %s when you finish your answer. Tell the administrator in one sentence.', $url), 'url' => $url];
    }

    /**
     * The admin menu entries the current administrator can open, keyed by menu path.
     *
     * @return array<string, array{title: string, action: string, acl: string}>
     */
    public function pages(): array
    {
        if ($this->pages === null) {
            $this->pages = [];
            $menu = \Mage::getSingleton('admin/config')->getAdminhtmlConfig()->getNode('menu');
            if ($menu instanceof \Maho\Simplexml\Element) {
                $this->collect(new \Mage_Adminhtml_Block_Page_Menu(), $menu, '', []);
            }
        }

        return $this->pages;
    }

    /** @param list<string> $titles */
    private function collect(\Mage_Adminhtml_Block_Page_Menu $menu, \Maho\Simplexml\Element $parent, string $path, array $titles): void
    {
        foreach ($parent->children() as $name => $child) {
            $resource = $menu->getItemAclResource($child, $path . $name);
            if (!$menu->isItemVisible($child, $resource)) {
                continue;
            }

            $module = (string) ($child->attributes()['module'] ?? 'adminhtml');
            $childTitles = [...$titles, (string) \Mage::helper($module)->__((string) $child->title)];
            $action = trim((string) $child->action, '/');
            if ($action !== '') {
                $this->pages[$path . $name] = ['title' => implode(' > ', $childTitles), 'action' => $action, 'acl' => substr($resource, strlen('admin/'))];
            }
            if ($child->children) {
                $this->collect($menu, $child->children, $path . $name . '/', $childTitles);
            }
        }
    }

    /**
     * The edit or view action of the controller behind a menu action, with its id parameter.
     *
     * @return array{string, string}|null route and id parameter name
     */
    private function recordRoute(string $menuAction): ?array
    {
        foreach (['edit', 'view'] as $action) {
            $resolved = $this->resolveAction($menuAction, $action);
            if ($resolved !== null) {
                return [$resolved['route'], $this->idParameter($resolved['class'], $action . 'Action')];
            }
        }

        return null;
    }

    /** The route of one action of the controller behind a menu action, when the controller has it. */
    private function actionRoute(string $menuAction, string $action): ?string
    {
        return $this->resolveAction($menuAction, $action)['route'] ?? null;
    }

    /** @return array{route: string, class: string}|null */
    private function resolveAction(string $menuAction, string $action): ?array
    {
        $parts = explode('/', $menuAction);
        $routeName = $parts[0];
        $controller = $parts[1] ?? 'index';
        $frontName = RouteCollectionBuilder::getFrontNameByRoute($routeName) ?? $routeName;
        $route = RouteCollectionBuilder::resolveRoute($frontName, $controller, $action);
        $class = $route['class'] ?? null;
        if ($class === null) {
            // An action inherited from a base controller has no compiled route, but the dispatcher serves it.
            $class = RouteCollectionBuilder::lookupCompiledControllerClass($frontName, $controller);
            if ($class === null || !method_exists($class, $action . 'Action')) {
                return null;
            }
        }

        return ['route' => $routeName . '/' . $controller . '/' . $action, 'class' => $class];
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

    /** API resource path segment => ACL resource of its admin page, from the API resource classes. */
    private ?array $resourceAcl = null;

    /**
     * Turn the API links of an answer into admin page links, so a record name in the
     * answer opens its edit page: [Blue Shirt](/api/rest/v2/products/12). An unknown
     * resource keeps its text and loses the link.
     */
    public function linkRecords(string $markdown): string
    {
        return (string) preg_replace_callback(
            '~\\[([^\\]\\n]*)\\]\\((?:https?://[^/\\s)]+)?/api/rest/v2/([a-z0-9-]+)/([A-Za-z0-9_.%-]+)/?\\)~',
            function (array $m): string {
                $url = $this->recordUrl($m[2], rawurldecode($m[3]));

                return $url === null ? $m[1] : sprintf('[%s](%s)', $m[1], $url);
            },
            $markdown,
        );
    }

    /**
     * The admin page of one record, or null when the resource names no admin page or the
     * administrator may not open it. An API resource class names its admin ACL resource in
     * ADMIN_RESOURCE, and the admin menu names the ACL resource of each entry: the two meet here.
     */
    public function recordUrl(string $resource, string $id): ?string
    {
        $acl = $this->resourceAcl()[$resource] ?? null;
        if ($acl === null) {
            return null;
        }
        foreach ($this->pages() as $page) {
            if ($page['acl'] !== $acl) {
                continue;
            }
            $record = $this->recordRoute($page['action']);
            if ($record === null) {
                continue;
            }
            [$route, $idParam] = $record;

            return \Mage::helper('adminhtml')->getUrl($route, [$idParam => $this->cleanValue($id)]);
        }

        return null;
    }

    /** @return array<string, string> */
    private function resourceAcl(): array
    {
        if ($this->resourceAcl !== null) {
            return $this->resourceAcl;
        }
        $this->resourceAcl = [];
        foreach ($this->resourceNameCollectionFactory->create() as $class) {
            if (!defined($class . '::ADMIN_RESOURCE')) {
                continue;
            }
            $acl = constant($class . '::ADMIN_RESOURCE');
            if (!is_string($acl) || $acl === '') {
                continue;
            }
            foreach ($this->resourceMetadataCollectionFactory->create($class) as $resource) {
                foreach ($resource->getOperations() ?? [] as $operation) {
                    if ($operation instanceof HttpOperation && preg_match('~^/([a-z0-9-]+)/\\{[^}]+\\}$~', (string) $operation->getUriTemplate(), $m) === 1) {
                        $this->resourceAcl[$m[1]] ??= $acl;
                    }
                }
            }
        }

        return $this->resourceAcl;
    }

    private function cleanValue(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_\-.]/', '', $value);
    }
}
