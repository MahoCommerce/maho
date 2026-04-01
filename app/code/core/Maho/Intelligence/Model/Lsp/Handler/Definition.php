<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Lsp_Handler_Definition
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

    public function handle(array $params): array|null
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

        if ($resolved['file'] === null) {
            return null;
        }

        return [
            'uri' => 'file://' . $resolved['file'],
            'range' => [
                'start' => ['line' => 0, 'character' => 0],
                'end' => ['line' => 0, 'character' => 0],
            ],
        ];
    }
}
