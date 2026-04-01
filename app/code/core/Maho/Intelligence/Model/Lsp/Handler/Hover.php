<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Lsp_Handler_Hover
{
    private Maho_Intelligence_Model_Registry $registry;
    private Maho_Intelligence_Model_Lsp_ContextDetector $detector;
    private Maho_Intelligence_Model_Lsp_DocumentStore $documents;

    public function __construct(
        Maho_Intelligence_Model_Registry $registry,
        Maho_Intelligence_Model_Lsp_ContextDetector $detector,
        Maho_Intelligence_Model_Lsp_DocumentStore $documents,
    ) {
        $this->registry = $registry;
        $this->detector = $detector;
        $this->documents = $documents;
    }

    public function handle(array $params): ?array
    {
        $uri = $params['textDocument']['uri'] ?? '';
        $line = $params['position']['line'] ?? 0;
        $character = $params['position']['character'] ?? 0;

        $text = $this->documents->get($uri);
        if ($text === null) {
            return null;
        }

        $context = $this->detector->detectAtCursor($text, $line, $character);
        if ($context === null) {
            return null;
        }

        return match ($context['context']) {
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_MODEL_ALIAS,
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_HELPER_ALIAS,
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_BLOCK_ALIAS,
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_RESOURCE_MODEL_ALIAS
                => $this->hoverAlias($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_EVENT_NAME
                => $this->hoverEvent($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_CONFIG_PATH
                => $this->hoverConfigPath($context),
            default => null,
        };
    }

    private function hoverAlias(array $context): ?array
    {
        $type = match ($context['context']) {
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_MODEL_ALIAS => 'model',
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_HELPER_ALIAS => 'helper',
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_BLOCK_ALIAS => 'block',
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_RESOURCE_MODEL_ALIAS => 'resource_model',
            default => null,
        };

        if ($type === null) {
            return null;
        }

        /** @var Maho_Intelligence_Model_Provider_ClassAlias $provider */
        $provider = $this->registry->getProvider('classAlias');
        $resolved = $provider->resolveAlias($type, $context['alias']);

        $markdown = "**{$type}**: `{$context['alias']}`\n\n";
        $markdown .= "**Class**: `{$resolved['class']}`\n\n";

        if ($resolved['file']) {
            $relativePath = str_replace(BP . '/', '', $resolved['file']);
            $markdown .= "**File**: `{$relativePath}`\n\n";
        }

        if ($resolved['rewritten_by']) {
            $markdown .= "**Rewritten by**: `{$resolved['rewritten_by']}`\n";
        }

        return [
            'contents' => [
                'kind' => 'markdown',
                'value' => $markdown,
            ],
        ];
    }

    private function hoverEvent(array $context): array
    {
        /** @var Maho_Intelligence_Model_Provider_Event $provider */
        $provider = $this->registry->getProvider('event');
        $observers = $provider->getObserversForEvent($context['alias']);

        if (empty($observers)) {
            return [
                'contents' => [
                    'kind' => 'markdown',
                    'value' => "**Event**: `{$context['alias']}`\n\nNo observers registered.",
                ],
            ];
        }

        $markdown = "**Event**: `{$context['alias']}`\n\n";
        foreach ($observers as $area => $areaObservers) {
            $markdown .= "**{$area}**:\n";
            foreach ($areaObservers as $observer) {
                $markdown .= "- `{$observer['class']}::{$observer['method']}` ({$observer['name']})\n";
            }
            $markdown .= "\n";
        }

        return [
            'contents' => [
                'kind' => 'markdown',
                'value' => $markdown,
            ],
        ];
    }

    private function hoverConfigPath(array $context): array
    {
        /** @var Maho_Intelligence_Model_Provider_ConfigPath $provider */
        $provider = $this->registry->getProvider('configPath');
        $info = $provider->getPathInfo($context['alias']);

        $markdown = "**Config path**: `{$context['alias']}`\n\n";

        if ($info) {
            $markdown .= "**Label**: {$info['label']}\n\n";
            $markdown .= "**Location**: {$info['section_label']} > {$info['group_label']}\n\n";
            if ($info['type']) {
                $markdown .= "**Type**: {$info['type']}\n\n";
            }
            if ($info['default'] !== null) {
                $markdown .= "**Default**: `{$info['default']}`\n";
            }
        } else {
            $node = Mage::getConfig()->getNode("default/{$context['alias']}");
            if ($node !== false) {
                $markdown .= "**Current value**: `{$node}`\n";
            } else {
                $markdown .= "Path not found in system.xml or config.\n";
            }
        }

        return [
            'contents' => [
                'kind' => 'markdown',
                'value' => $markdown,
            ],
        ];
    }
}
