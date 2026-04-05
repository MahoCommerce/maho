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
 * Scans document text for Maho class alias patterns and reports
 * diagnostics for aliases that cannot be resolved.
 *
 * Supports both PHP and XML files.
 */
class Maho_Intelligence_Model_Lsp_Handler_Diagnostic
{
    private const SEVERITY_WARNING = 2;

    private const PHP_ALIAS_PATTERNS = [
        'model' => [
            '/Mage::getModel\(\s*[\'"]([^\'"]+)[\'"]\)/',
            '/Mage::getSingleton\(\s*[\'"]([^\'"]+)[\'"]\)/',
        ],
        'helper' => [
            '/Mage::helper\(\s*[\'"]([^\'"]+)[\'"]\)/',
        ],
        'block' => [
            '/->createBlock\(\s*[\'"]([^\'"]+)[\'"]\)/',
            '/->getBlockSingleton\(\s*[\'"]([^\'"]+)[\'"]\)/',
        ],
        'resource_model' => [
            '/Mage::getResourceModel\(\s*[\'"]([^\'"]+)[\'"]\)/',
            '/Mage::getResourceSingleton\(\s*[\'"]([^\'"]+)[\'"]\)/',
        ],
    ];

    private const XML_ALIAS_TAG_PATTERNS = [
        'model' => [
            '/<class>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)<\/class>/i',
            '/<source_model>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)<\/source_model>/i',
            '/<backend_model>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)<\/backend_model>/i',
        ],
        'block' => [
            '/type=["\']([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)["\']/',
            '/<render>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)<\/render>/i',
            '/<renderer>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)<\/renderer>/i',
            '/<frontend_model>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)<\/frontend_model>/i',
        ],
    ];

    private Maho_Intelligence_Model_Registry $registry;

    public function __construct(Maho_Intelligence_Model_Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return array LSP diagnostic items
     */
    public function diagnose(string $uri, string $text): array
    {
        if (Maho_Intelligence_Model_Lsp_ContextDetector::isXmlUri($uri)) {
            return $this->diagnoseXml($text);
        }
        return $this->diagnosePhp($text);
    }

    private function diagnosePhp(string $text): array
    {
        $diagnostics = [];
        $lines = explode("\n", $text);

        foreach ($lines as $lineNum => $line) {
            foreach (self::PHP_ALIAS_PATTERNS as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[1] as $match) {
                            $alias = $match[0];
                            $offset = $match[1];
                            $resolved = $this->registry->get('classAlias', 'resolveAlias', [$type, $alias]);

                            if ($resolved['class'] === null || $resolved['file'] === null) {
                                $diagnostics[] = $this->diagnostic(
                                    $lineNum,
                                    $offset,
                                    $alias,
                                    "Unresolved {$type} alias: '{$alias}'",
                                );
                            }
                        }
                    }
                }
            }
        }

        return $diagnostics;
    }

    private function diagnoseXml(string $text): array
    {
        $diagnostics = [];
        $lines = explode("\n", $text);

        foreach ($lines as $lineNum => $line) {
            foreach (self::XML_ALIAS_TAG_PATTERNS as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[1] as $match) {
                            $alias = $match[0];
                            $offset = $match[1];
                            $resolved = $this->registry->get('classAlias', 'resolveAlias', [$type, $alias]);

                            if ($resolved['class'] === null || $resolved['file'] === null) {
                                $diagnostics[] = $this->diagnostic(
                                    $lineNum,
                                    $offset,
                                    $alias,
                                    "Unresolved {$type} alias: '{$alias}'",
                                );
                            }
                        }
                    }
                }
            }

            // Check cron model::method patterns
            if (preg_match_all('/<model>([a-z][a-z0-9]*\/[a-z][a-z0-9_]*)::(\w+)<\/model>/i', $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $alias = $match[1][0];
                    $offset = $match[1][1];
                    $resolved = $this->registry->get('classAlias', 'resolveAlias', ['model', $alias]);

                    if ($resolved['class'] === null || $resolved['file'] === null) {
                        $diagnostics[] = $this->diagnostic(
                            $lineNum,
                            $offset,
                            $alias,
                            "Unresolved model alias in cron callback: '{$alias}'",
                        );
                    }
                }
            }
        }

        return $diagnostics;
    }

    private function diagnostic(int $line, int|string $offset, string $alias, string $message): array
    {
        return [
            'range' => [
                'start' => ['line' => $line, 'character' => (int) $offset],
                'end' => ['line' => $line, 'character' => (int) $offset + strlen($alias)],
            ],
            'severity' => self::SEVERITY_WARNING,
            'source' => 'maho-intelligence',
            'message' => $message,
        ];
    }
}
