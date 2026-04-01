<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Lsp_Handler_Completion
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
            return ['isIncomplete' => false, 'items' => []];
        }

        $context = $this->detector->detect($text, $line, $character);

        return match ($context['context']) {
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_MODEL_ALIAS
                => $this->completeAliases('model', $context['prefix']),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_HELPER_ALIAS
                => $this->completeAliases('helper', $context['prefix']),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_BLOCK_ALIAS
                => $this->completeAliases('block', $context['prefix']),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_RESOURCE_MODEL_ALIAS
                => $this->completeAliases('resource_model', $context['prefix']),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_CONFIG_PATH
                => $this->completeConfigPaths($context['prefix']),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_EVENT_NAME
                => $this->completeEventNames($context['prefix']),
            default => ['isIncomplete' => false, 'items' => []],
        };
    }

    private function completeAliases(string $type, string $prefix): array
    {
        /** @var Maho_Intelligence_Model_Provider_ClassAlias $provider */
        $provider = $this->registry->getProvider('classAlias');
        $aliases = $provider->getAllAliases($type);

        $items = [];
        foreach ($aliases as $alias => $info) {
            if ($prefix !== '' && !str_starts_with($alias, $prefix)) {
                continue;
            }

            $items[] = [
                'label' => $alias,
                'kind' => 6, // CompletionItemKind.Variable
                'detail' => $info['class'],
                'documentation' => $info['class'],
                'insertText' => $alias,
            ];

            if (count($items) >= 200) {
                return ['isIncomplete' => true, 'items' => $items];
            }
        }

        return ['isIncomplete' => false, 'items' => $items];
    }

    private function completeConfigPaths(string $prefix): array
    {
        /** @var Maho_Intelligence_Model_Provider_ConfigPath $provider */
        $provider = $this->registry->getProvider('configPath');
        $paths = $provider->getAllPaths();

        $items = [];
        foreach ($paths as $path => $info) {
            if ($prefix !== '' && !str_starts_with($path, $prefix)) {
                continue;
            }

            $detail = $info['label'];
            if ($info['default'] !== null) {
                $detail .= ' (default: ' . $info['default'] . ')';
            }

            $items[] = [
                'label' => $path,
                'kind' => 10, // CompletionItemKind.Property
                'detail' => $detail,
                'documentation' => "{$info['section_label']} > {$info['group_label']} > {$info['label']}",
                'insertText' => $path,
            ];

            if (count($items) >= 200) {
                return ['isIncomplete' => true, 'items' => $items];
            }
        }

        return ['isIncomplete' => false, 'items' => $items];
    }

    private function completeEventNames(string $prefix): array
    {
        /** @var Maho_Intelligence_Model_Provider_Event $provider */
        $provider = $this->registry->getProvider('event');
        $allEvents = $provider->getAllEvents();

        $seen = [];
        $items = [];
        foreach ($allEvents as $area => $events) {
            foreach ($events as $name => $event) {
                if (isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;

                if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                    continue;
                }

                $observerCount = count($event['observers']);
                $items[] = [
                    'label' => $name,
                    'kind' => 23, // CompletionItemKind.Event
                    'detail' => "{$observerCount} observer(s) in {$area}",
                    'insertText' => $name,
                ];

                if (count($items) >= 200) {
                    return ['isIncomplete' => true, 'items' => $items];
                }
            }
        }

        return ['isIncomplete' => false, 'items' => $items];
    }
}
