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
use Symfony\AI\Platform\Result\ToolCall;

final class McpToolbox implements ToolboxInterface
{
    /** A failed call is reported to the model as text that starts with this. */
    public const ERROR_PREFIX = 'Tool error: ';

    public static function isErrorText(string $text): bool
    {
        return str_starts_with($text, self::ERROR_PREFIX);
    }

    /** @var list<string>|null */
    private ?array $excludedSections = null;

    private ?int $resultMaxChars = null;

    private ?string $navigation = null;

    public function __construct(
        private readonly McpToolCatalog $catalog,
        private readonly McpToolDispatcher $dispatcher,
        private readonly AdminPageTool $adminPageTool,
    ) {}

    #[\Override]
    public function getTools(): array
    {
        return [...$this->catalog->tools($this->excludedSections()), $this->adminPageTool->tool()];
    }

    #[\Override]
    public function execute(ToolCall $toolCall): ToolResult
    {
        if ($toolCall->getName() === AdminPageTool::NAME) {
            $outcome = $this->adminPageTool->open($toolCall->getArguments());
            $this->navigation = $outcome['url'] ?? null;
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
        $codes = array_map(static fn(\Mage_Core_Model_Store $s): string => (string) $s->getCode(), array_values($stores));

        return ['ok' => false, 'text' => sprintf('Unknown store view code "%s". Store view codes: %s.', $storeCode, implode(', ', $codes))];
    }

    /** The admin URL the last local tool asked the browser to open, once. */
    public function takeNavigation(): ?string
    {
        $url = $this->navigation;
        $this->navigation = null;

        return $url;
    }

    /** A tool that never changes data, so it runs without the administrator's confirmation. */
    public function isReadOnly(string $name): bool
    {
        return $name === AdminPageTool::NAME || $this->catalog->isReadOnly($name);
    }

    public function isDestructive(string $name): bool
    {
        return $name !== AdminPageTool::NAME && $this->catalog->isDestructive($name);
    }

    public function title(string $name): string
    {
        return $name === AdminPageTool::NAME ? 'Open admin page' : $this->catalog->title($name);
    }

    public function resolve(string $name): string
    {
        return $name === AdminPageTool::NAME ? $name : $this->catalog->resolve($name);
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
