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

        $context = $this->detector->detectAtCursor($text, $line, $character, $uri);
        if ($context === null) {
            return null;
        }

        return match ($context['context']) {
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_MODEL_ALIAS,
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_HELPER_ALIAS,
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_BLOCK_ALIAS,
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_RESOURCE_MODEL_ALIAS
                => $this->definitionForAlias($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_FQCN
                => $this->definitionForFqcn($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_XML_METHOD
                => $this->definitionForMethod($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_CRON_RUN_MODEL
                => $this->definitionForCronModel($context),
            Maho_Intelligence_Model_Lsp_ContextDetector::CONTEXT_TEMPLATE_PATH
                => $this->definitionForTemplate($context),
            default => null,
        };
    }

    private function definitionForAlias(array $context): ?array
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

        if ($resolved['file'] === null) {
            return null;
        }

        return $this->fileLocation($resolved['file']);
    }

    private function definitionForFqcn(array $context): ?array
    {
        $file = Maho::findClassFile($context['alias']);

        return $file !== false ? $this->fileLocation($file) : null;
    }

    private function definitionForMethod(array $context): ?array
    {
        $classAlias = $context['classAlias'] ?? null;
        $classType = $context['classType'] ?? 'model';
        $method = $context['method'] ?? null;

        if ($classAlias === null || $method === null) {
            return null;
        }

        $resolved = $this->registry->get('classAlias', 'resolveAlias', [$classType, $classAlias]);
        if ($resolved['file'] === null) {
            return null;
        }

        $methodLine = $this->findMethodLine($resolved['file'], $method);

        return $this->fileLocation($resolved['file'], $methodLine);
    }

    private function definitionForCronModel(array $context): ?array
    {
        $classAlias = $context['classAlias'] ?? null;
        $method = $context['method'] ?? null;

        if ($classAlias === null) {
            return null;
        }

        $resolved = $this->registry->get('classAlias', 'resolveAlias', ['model', $classAlias]);
        if ($resolved['file'] === null) {
            return null;
        }

        $methodLine = $method !== null ? $this->findMethodLine($resolved['file'], $method) : 0;

        return $this->fileLocation($resolved['file'], $methodLine);
    }

    private function definitionForTemplate(array $context): ?array
    {
        $templatePath = $context['alias'];
        $designPackage = Mage::getSingleton('core/design_package');

        $file = $designPackage->getTemplateFilename($templatePath, ['_area' => 'frontend']);
        if (!file_exists($file)) {
            $file = $designPackage->getTemplateFilename($templatePath, ['_area' => 'admin']);
        }

        return file_exists($file) ? $this->fileLocation($file) : null;
    }

    private function fileLocation(string $filePath, int $line = 0): array
    {
        return [
            'uri' => 'file://' . $filePath,
            'range' => [
                'start' => ['line' => $line, 'character' => 0],
                'end' => ['line' => $line, 'character' => 0],
            ],
        ];
    }

    private function findMethodLine(string $filePath, string $method): int
    {
        $lines = @file($filePath);
        if ($lines === false) {
            return 0;
        }

        $pattern = '/function\s+' . preg_quote($method, '/') . '\s*\(/';
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                return $i;
            }
        }

        return 0;
    }
}
