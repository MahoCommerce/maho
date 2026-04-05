<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Detects what kind of Maho context the cursor is in, enabling
 * context-aware completions, definitions, and hover info.
 */
class Maho_Intelligence_Model_Lsp_ContextDetector
{
    public const CONTEXT_NONE = 'none';
    public const CONTEXT_MODEL_ALIAS = 'model_alias';
    public const CONTEXT_HELPER_ALIAS = 'helper_alias';
    public const CONTEXT_BLOCK_ALIAS = 'block_alias';
    public const CONTEXT_RESOURCE_MODEL_ALIAS = 'resource_model_alias';
    public const CONTEXT_CONFIG_PATH = 'config_path';
    public const CONTEXT_EVENT_NAME = 'event_name';

    private const CALL_PATTERNS = [
        self::CONTEXT_MODEL_ALIAS => [
            'Mage::getModel',
            'Mage::getSingleton',
        ],
        self::CONTEXT_HELPER_ALIAS => [
            'Mage::helper',
        ],
        self::CONTEXT_BLOCK_ALIAS => [
            '->createBlock',
            '->getBlockSingleton',
            'getLayout()->createBlock',
        ],
        self::CONTEXT_RESOURCE_MODEL_ALIAS => [
            'Mage::getResourceModel',
            'Mage::getResourceSingleton',
        ],
        self::CONTEXT_CONFIG_PATH => [
            'Mage::getStoreConfig',
            'Mage::getStoreConfigFlag',
        ],
        self::CONTEXT_EVENT_NAME => [
            'Mage::dispatchEvent',
        ],
    ];

    /** @var array<string, list<string>>|null */
    private ?array $completionPatterns = null;

    /** @var array<string, list<string>>|null */
    private ?array $cursorPatterns = null;

    /**
     * @return array<string, list<string>>
     */
    private function getCompletionPatterns(): array
    {
        if ($this->completionPatterns === null) {
            $this->completionPatterns = [];
            foreach (self::CALL_PATTERNS as $context => $calls) {
                foreach ($calls as $call) {
                    $escaped = preg_quote($call, '/');
                    $this->completionPatterns[$context][] = "/{$escaped}\\(\\s*['\"]([^'\"]*)?$/";
                }
            }
        }
        return $this->completionPatterns;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getCursorPatterns(): array
    {
        if ($this->cursorPatterns === null) {
            $this->cursorPatterns = [];
            foreach (self::CALL_PATTERNS as $context => $calls) {
                foreach ($calls as $call) {
                    $escaped = preg_quote($call, '/');
                    $this->cursorPatterns[$context][] = "/{$escaped}\\(\\s*['\"]([^'\"]+)['\"]\\)/";
                }
            }
        }
        return $this->cursorPatterns;
    }

    /**
     * Detect what context the cursor is in for completion.
     *
     * @return array{context: string, prefix: string, prefixStart: int}
     */
    public function detect(string $text, int $line, int $character): array
    {
        $lines = explode("\n", $text);
        if (!isset($lines[$line])) {
            return ['context' => self::CONTEXT_NONE, 'prefix' => '', 'prefixStart' => $character];
        }

        $textBeforeCursor = substr($lines[$line], 0, $character);

        foreach ($this->getCompletionPatterns() as $context => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $textBeforeCursor, $matches)) {
                    $prefix = $matches[1] ?? '';
                    return [
                        'context' => $context,
                        'prefix' => $prefix,
                        'prefixStart' => $character - strlen($prefix),
                    ];
                }
            }
        }

        return ['context' => self::CONTEXT_NONE, 'prefix' => '', 'prefixStart' => $character];
    }

    /**
     * Detect context for an existing alias string at cursor position (for definition/hover).
     * Looks for a complete quoted string containing the cursor.
     *
     * @return array{context: string, alias: string}|null
     */
    public function detectAtCursor(string $text, int $line, int $character): ?array
    {
        $lines = explode("\n", $text);
        if (!isset($lines[$line])) {
            return null;
        }

        $lineText = $lines[$line];

        foreach ($this->getCursorPatterns() as $context => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $lineText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($matches as $match) {
                        $aliasStart = $match[1][1];
                        $aliasEnd = $aliasStart + strlen($match[1][0]);
                        if ($character >= $aliasStart && $character <= $aliasEnd) {
                            return [
                                'context' => $context,
                                'alias' => $match[1][0],
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }
}
