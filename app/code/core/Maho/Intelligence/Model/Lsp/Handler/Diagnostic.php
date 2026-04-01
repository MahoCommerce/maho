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
 */
class Maho_Intelligence_Model_Lsp_Handler_Diagnostic
{
    private const SEVERITY_WARNING = 2;

    private const ALIAS_PATTERNS = [
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
        $diagnostics = [];
        $lines = explode("\n", $text);

        /** @var Maho_Intelligence_Model_Provider_ClassAlias $provider */
        $provider = $this->registry->getProvider('classAlias');

        foreach ($lines as $lineNum => $line) {
            foreach (self::ALIAS_PATTERNS as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[1] as $match) {
                            $alias = $match[0];
                            $offset = $match[1];
                            $resolved = $provider->resolveAlias($type, $alias);

                            if ($resolved['class'] === null || $resolved['file'] === null) {
                                $diagnostics[] = [
                                    'range' => [
                                        'start' => ['line' => $lineNum, 'character' => $offset],
                                        'end' => ['line' => $lineNum, 'character' => $offset + strlen($alias)],
                                    ],
                                    'severity' => self::SEVERITY_WARNING,
                                    'source' => 'maho-intelligence',
                                    'message' => "Unresolved {$type} alias: '{$alias}'",
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $diagnostics;
    }
}
