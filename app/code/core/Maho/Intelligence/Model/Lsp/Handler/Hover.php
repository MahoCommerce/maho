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

        $context = $this->detector->detectAtCursor($text, $line, $character, $uri);
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
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_FQCN
                => $this->hoverFqcn($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_XML_METHOD
                => $this->hoverMethod($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_CRON_RUN_MODEL
                => $this->hoverCronModel($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_TEMPLATE_PATH
                => $this->hoverTemplate($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_LAYOUT_HANDLE
                => $this->hoverLayoutHandle($context),
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

        $resolved = $this->registry->get('classAlias', 'resolveAlias', [$type, $context['alias']]);

        $markdown = "**{$type}**: `{$context['alias']}`\n\n";
        $markdown .= "**Class**: `{$resolved['class']}`\n\n";

        if ($resolved['file']) {
            $relativePath = str_replace(BP . '/', '', $resolved['file']);
            $markdown .= "**File**: `{$relativePath}`\n\n";
        }

        if ($resolved['rewritten_by']) {
            $markdown .= "**Rewritten by**: `{$resolved['rewritten_by']}`\n";
        }

        return $this->markdown($markdown);
    }

    private function hoverEvent(array $context): array
    {
        $observers = $this->registry->get('event', 'getObserversForEvent', [$context['alias']]);

        if (empty($observers)) {
            return $this->markdown("**Event**: `{$context['alias']}`\n\nNo observers registered.");
        }

        $markdown = "**Event**: `{$context['alias']}`\n\n";
        foreach ($observers as $area => $areaObservers) {
            $markdown .= "**{$area}**:\n";
            foreach ($areaObservers as $observer) {
                $markdown .= "- `{$observer['class']}::{$observer['method']}` ({$observer['name']})\n";
            }
            $markdown .= "\n";
        }

        return $this->markdown($markdown);
    }

    private function hoverConfigPath(array $context): array
    {
        $info = $this->registry->get('configPath', 'getPathInfo', [$context['alias']]);

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

        return $this->markdown($markdown);
    }

    private function hoverFqcn(array $context): array
    {
        $className = $context['alias'];
        $file = Maho::findClassFile($className);

        $markdown = "**Class**: `{$className}`\n\n";

        if ($file !== false) {
            $relativePath = str_replace(BP . '/', '', $file);
            $markdown .= "**File**: `{$relativePath}`\n";
        } else {
            $markdown .= "Class file not found.\n";
        }

        return $this->markdown($markdown);
    }

    private function hoverMethod(array $context): array
    {
        $classAlias = $context['classAlias'] ?? null;
        $classType = $context['classType'] ?? 'model';
        $method = $context['method'] ?? $context['alias'];

        if ($classAlias === null) {
            return $this->markdown("**Method**: `{$method}`\n\nCould not determine parent class.");
        }

        $resolved = $this->registry->get('classAlias', 'resolveAlias', [$classType, $classAlias]);

        $markdown = "**Method**: `{$method}`\n\n";
        $markdown .= "**Class**: `{$resolved['class']}` (`{$classAlias}`)\n\n";

        if ($resolved['file']) {
            $relativePath = str_replace(BP . '/', '', $resolved['file']);
            $markdown .= "**File**: `{$relativePath}`\n\n";

            $signature = $this->getMethodSignature($resolved['file'], $method);
            if ($signature !== null) {
                $markdown .= "```php\n{$signature}\n```\n";
            } else {
                $markdown .= "Method `{$method}` not found in class.\n";
            }
        }

        return $this->markdown($markdown);
    }

    private function hoverCronModel(array $context): ?array
    {
        $classAlias = $context['classAlias'] ?? null;
        $method = $context['method'] ?? null;

        if ($classAlias === null) {
            return null;
        }

        $resolved = $this->registry->get('classAlias', 'resolveAlias', ['model', $classAlias]);

        $markdown = "**Cron callback**: `{$context['alias']}`\n\n";
        $markdown .= "**Class**: `{$resolved['class']}` (`{$classAlias}`)\n\n";

        if ($resolved['file']) {
            $relativePath = str_replace(BP . '/', '', $resolved['file']);
            $markdown .= "**File**: `{$relativePath}`\n\n";

            if ($method !== null) {
                $signature = $this->getMethodSignature($resolved['file'], $method);
                if ($signature !== null) {
                    $markdown .= "```php\n{$signature}\n```\n";
                } else {
                    $markdown .= "Method `{$method}` not found in class.\n";
                }
            }
        }

        return $this->markdown($markdown);
    }

    private function hoverTemplate(array $context): array
    {
        $templatePath = $context['alias'];
        $markdown = "**Template**: `{$templatePath}`\n\n";

        $file = Maho::findFile('app/design/frontend/base/default/template/' . $templatePath);
        if ($file === false) {
            $file = Maho::findFile('app/design/adminhtml/default/default/template/' . $templatePath);
        }

        if ($file !== false) {
            $relativePath = str_replace(BP . '/', '', $file);
            $markdown .= "**File**: `{$relativePath}`\n";
        } else {
            $markdown .= "Template file not found.\n";
        }

        return $this->markdown($markdown);
    }

    private function hoverLayoutHandle(array $context): array
    {
        $handle = $context['alias'];
        $markdown = "**Layout handle**: `{$handle}`\n\n";

        $frontendHandles = $this->registry->get('layout', 'getHandles', ['frontend']);
        $adminhtmlHandles = $this->registry->get('layout', 'getHandles', ['admin']);

        $info = $frontendHandles[$handle] ?? $adminhtmlHandles[$handle] ?? null;
        if ($info !== null) {
            $markdown .= "**Defined in**: `{$info['file']}`\n\n";
            if (!empty($info['blocks'])) {
                $markdown .= '**Blocks**: ' . count($info['blocks']) . "\n";
            }
        } else {
            $markdown .= "Handle not found in layout configuration.\n";
        }

        return $this->markdown($markdown);
    }

    private function markdown(string $value): array
    {
        return [
            'contents' => [
                'kind' => 'markdown',
                'value' => $value,
            ],
        ];
    }

    private function getMethodSignature(string $filePath, string $method): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $lines = file($filePath);
        if ($lines === false) {
            return null;
        }

        $pattern = '/function\s+' . preg_quote($method, '/') . '\s*\(/';
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                $signature = trim($line);
                // If the signature continues on the next line (multi-line params)
                if (!str_contains($signature, ')') && isset($lines[$i + 1])) {
                    for ($j = $i + 1; $j < min($i + 10, count($lines)); $j++) {
                        $signature .= ' ' . trim($lines[$j]);
                        if (str_contains($lines[$j], ')')) {
                            break;
                        }
                    }
                }
                // Trim to just the signature (up to and including the closing brace/semicolon)
                if (preg_match('/^(.*?\))/', $signature, $m)) {
                    return $m[1];
                }
                return $signature;
            }
        }

        return null;
    }
}
