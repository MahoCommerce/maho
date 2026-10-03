<?php

/**
 * Symfony AI toolbox backed by the in-process MCP tools.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Maho\ApiPlatform\Service\StoreContext;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * The MCP tools are grouped in sections and loaded on demand: a model request with every
 * tool is too large for most providers, and OpenAI-compatible endpoints stop at 128 tools.
 * The agent starts with the local tools and the sections the chat service enabled from the
 * page and the conversation; enable_tools loads more, and the agent runs again with them.
 */
final class McpToolbox implements ToolboxInterface
{
    /** A failed call is reported to the model as text that starts with this. */
    public const ERROR_PREFIX = 'Tool error: ';

    public const ENABLE_NAME = 'enable_tools';

    /** The most tools one model request carries, under the 128 that OpenAI-compatible endpoints accept. */
    public const MAX_TOOLS = 120;

    public static function isErrorText(string $text): bool
    {
        return str_starts_with($text, self::ERROR_PREFIX);
    }

    /** @var list<string>|null */
    private ?array $excludedSections = null;

    private ?int $resultMaxChars = null;

    /** @var array{url?: string, fields?: array<string, mixed>, steps?: list<array{action: string, target: string, value: string|null}>}|null */
    private ?array $navigation = null;

    /** @var list<string> enabled sections, most recent last */
    private array $enabledSections = [];

    private bool $toolsetChanged = false;

    public function __construct(
        private readonly McpToolCatalog $catalog,
        private readonly McpToolDispatcher $dispatcher,
        private readonly AdminPageTool $adminPageTool,
        private readonly ContentGuideTool $contentGuideTool,
    ) {}

    /** The editor guide the panel sent with this request, or an empty string without one. */
    public function setContentGuide(string $guide): void
    {
        $this->contentGuideTool->setGuide($guide);
    }

    #[\Override]
    public function getTools(): array
    {
        $tools = [$this->enableTool(), $this->adminPageTool->tool(), $this->adminPageTool->fillTool(), $this->adminPageTool->actionTool()];
        if ($this->contentGuideTool->hasGuide()) {
            $tools[] = $this->contentGuideTool->tool();
        }
        // The most recently enabled section wins the budget: it is the one the model asked for last.
        foreach (array_reverse($this->enabledSections) as $section) {
            $sectionTools = $this->catalog->tools($this->excludedSections(), [$section]);
            if (count($tools) + count($sectionTools) > self::MAX_TOOLS) {
                continue;
            }
            $tools = [...$tools, ...$sectionTools];
        }

        return $tools;
    }

    /**
     * Load sections before the agent runs: the ones the page context and the conversation
     * history point to. Unknown names are ignored.
     *
     * @param list<string> $sections
     */
    public function enableSections(array $sections): void
    {
        $known = array_keys($this->catalog->sections($this->excludedSections()));
        foreach ($sections as $section) {
            if (in_array($section, $known, true) && !in_array($section, $this->enabledSections, true)) {
                $this->enabledSections[] = $section;
            }
        }
    }

    /**
     * Every section the current administrator can load, with its tool names.
     *
     * @return array<string, list<string>>
     */
    public function sections(): array
    {
        return $this->catalog->sections($this->excludedSections());
    }

    /** @return list<string> */
    public function enabledSections(): array
    {
        return $this->enabledSections;
    }

    /** True once after enable_tools changed the set, so the executor can restart the run. */
    public function takeToolsetChange(): bool
    {
        $changed = $this->toolsetChanged;
        $this->toolsetChanged = false;

        return $changed;
    }

    private function enableTool(): Tool
    {
        $sections = $this->catalog->sections($this->excludedSections());
        $lines = [];
        foreach ($sections as $section => $names) {
            $state = in_array($section, $this->enabledSections, true) ? 'loaded' : 'not loaded';
            $lines[] = sprintf('%s (%s): %s', $section, $state, implode(', ', $names));
        }

        return new Tool(
            new ExecutionReference(self::class, 'execute'),
            self::ENABLE_NAME,
            'Load the tools of one or more sections. The store tools are grouped in sections and only the loaded sections are callable. Call this first when the tool you need is not loaded, then continue. Sections and their tools: ' . implode('; ', $lines) . '.',
            [
                'type' => 'object',
                'properties' => [
                    'sections' => [
                        'type' => 'array',
                        'description' => 'The sections to load.',
                        'items' => ['type' => 'string', 'enum' => array_keys($sections)],
                    ],
                ],
                'required' => ['sections'],
                'additionalProperties' => false,
            ],
            ['title' => 'Load tools', 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    private function enable(array $arguments): array
    {
        $known = array_keys($this->catalog->sections($this->excludedSections()));
        $wanted = array_values(array_filter((array) ($arguments['sections'] ?? []), is_string(...)));
        $unknown = array_diff($wanted, $known);
        if ($wanted === [] || $unknown !== []) {
            return ['ok' => false, 'text' => sprintf('Unknown section%s: %s. Sections: %s.', count($unknown) === 1 ? '' : 's', implode(', ', $unknown) ?: '(none given)', implode(', ', $known))];
        }
        $before = $this->enabledSections;
        $this->enableSections($wanted);
        $this->toolsetChanged = $this->enabledSections !== $before;

        return ['ok' => true, 'text' => sprintf('Loaded sections: %s. Their tools are callable from your next request. Loaded now: %s.', implode(', ', $wanted), implode(', ', $this->enabledSections))];
    }

    /** A tool that runs in this process, outside the MCP catalog. */
    public static function isLocal(string $name): bool
    {
        return $name === self::ENABLE_NAME || $name === ContentGuideTool::NAME || in_array($name, AdminPageTool::NAMES, true);
    }

    #[\Override]
    public function execute(ToolCall $toolCall): ToolResult
    {
        if ($toolCall->getName() === self::ENABLE_NAME) {
            $outcome = $this->enable($toolCall->getArguments());
        } elseif ($toolCall->getName() === ContentGuideTool::NAME) {
            $outcome = $this->contentGuideTool->read();
        } elseif (in_array($toolCall->getName(), AdminPageTool::NAMES, true)) {
            $outcome = match ($toolCall->getName()) {
                AdminPageTool::FILL_NAME => $this->adminPageTool->fill($toolCall->getArguments()),
                AdminPageTool::ACTION_NAME => $this->adminPageTool->act($toolCall->getArguments()),
                default => $this->adminPageTool->open($toolCall->getArguments()),
            };
            if (isset($outcome['url'])) {
                $this->navigation = ['url' => $outcome['url']];
                if (isset($outcome['fields'])) {
                    $this->navigation['fields'] = $outcome['fields'];
                }
            } elseif (isset($outcome['steps'])) {
                $this->navigation = ['steps' => $outcome['steps']];
            }
        } else {
            $name = $this->catalog->resolve($toolCall->getName());
            $arguments = $this->catalog->coerce($name, $toolCall->getArguments());
            $storeCode = $arguments[McpToolCatalog::STORE_ARGUMENT] ?? null;
            unset($arguments[McpToolCatalog::STORE_ARGUMENT]);
            $outcome = $this->inStore($storeCode, fn(): array => $this->dispatcher->call($name, $arguments));
        }
        $text = $outcome['ok'] ? $outcome['text'] : self::ERROR_PREFIX . $outcome['text'];

        return new ToolResult($toolCall, $this->truncate($text));
    }

    /**
     * @param \Closure(): array{ok: bool, text: string} $call
     * @return array{ok: bool, text: string}
     */
    private function inStore(mixed $storeCode, \Closure $call): array
    {
        if (!is_string($storeCode) || trim($storeCode) === '') {
            return $call();
        }
        $stores = \Mage::app()->getStores();
        foreach ($stores as $store) {
            if ($store->getCode() === trim($storeCode)) {
                return StoreContext::withExplicitStore((int) $store->getId(), $call);
            }
        }
        $codes = array_map(static fn(\Mage_Core_Model_Store $s): string => sprintf('%s (%s)', $s->getCode(), $s->getName()), array_values($stores));

        return ['ok' => false, 'text' => sprintf('Unknown store view code "%s". Store view codes: %s.', $storeCode, implode(', ', $codes))];
    }

    /**
     * What the last local tool asked the browser to do, once: open an admin page, with form
     * values to fill in when the tool gave some, or act on the open page.
     *
     * @return array{url?: string, fields?: array<string, mixed>, steps?: list<array{action: string, target: string, value: string|null}>}|null
     */
    public function takeNavigation(): ?array
    {
        $navigation = $this->navigation;
        $this->navigation = null;

        return $navigation;
    }

    /** A tool that never changes data, so it runs without the administrator's confirmation. */
    public function isReadOnly(string $name): bool
    {
        return self::isLocal($name) || $this->catalog->isReadOnly($name);
    }

    public function isDestructive(string $name): bool
    {
        return !self::isLocal($name) && $this->catalog->isDestructive($name);
    }

    public function title(string $name): string
    {
        return match (true) {
            $name === self::ENABLE_NAME => 'Load tools',
            $name === ContentGuideTool::NAME => ContentGuideTool::TITLE,
            in_array($name, AdminPageTool::NAMES, true) => AdminPageTool::title($name),
            default => $this->catalog->title($name),
        };
    }

    public function resolve(string $name): string
    {
        return self::isLocal($name) ? $name : $this->catalog->resolve($name);
    }

    /**
     * @return list<string>
     */
    private function excludedSections(): array
    {
        if ($this->excludedSections === null) {
            $raw = (string) \Mage::getStoreConfig('ai/chat/excluded_sections');
            $this->excludedSections = array_values(array_filter(array_map(trim(...), explode(',', $raw))));
        }

        return $this->excludedSections;
    }

    private function truncate(string $text): string
    {
        $max = $this->resultMaxChars ??= max(1000, (int) \Mage::getStoreConfig('ai/chat/tool_result_max_chars'));
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . sprintf("\n[truncated: %d more characters. Ask for a smaller page or fewer fields. Never write a truncated field back.]", mb_strlen($text) - $max);
    }
}
