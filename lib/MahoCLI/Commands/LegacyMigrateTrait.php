<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use DOMDocument;
use DOMElement;
use DOMNode;
use ReflectionClass;

/**
 * Shared helpers for the legacy:migrate-* commands.
 *
 * Each command discovers legacy XML declarations in user modules (app/code/local
 * and app/code/community), inserts the equivalent PHP attribute on the target
 * method, and removes the XML block. This trait holds the file/DOM/PHP plumbing
 * so each command can focus on its specific XML shape.
 */
trait LegacyMigrateTrait
{
    /**
     * @var list<string>
     */
    protected array $userCodeDirs = [
        'app/code/local',
        'app/code/community',
    ];

    /**
     * @return list<array{module: string, path: string}>
     */
    protected function findUserConfigXmlFiles(): array
    {
        $found = [];
        foreach ($this->userCodeDirs as $dir) {
            $base = MAHO_ROOT_DIR . '/' . $dir;
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->getFilename() !== 'config.xml') {
                    continue;
                }
                $path = $file->getPathname();
                $rel = str_replace(MAHO_ROOT_DIR . '/', '', $path);
                $parts = explode('/', $rel);
                // Expected layout: app/code/{pool}/{Vendor}/{Module}/etc/config.xml
                if (count($parts) < 7 || $parts[5] !== 'etc') {
                    continue;
                }
                $found[] = [
                    'module' => $parts[3] . '_' . $parts[4],
                    'path' => $path,
                ];
            }
        }
        return $found;
    }

    protected function loadConfigXmlAsDom(string $path): ?DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        libxml_use_internal_errors(true);
        $loaded = $dom->load($path);
        libxml_clear_errors();
        return $loaded ? $dom : null;
    }

    /**
     * Removes an element from its parent and bubbles up: if the parent now has
     * no element children, remove it too, and so on. Walks until we hit an
     * ancestor that still has element children, or the document root.
     *
     * Use this after detaching a leaf XML node (e.g. <observer_name>) to
     * collapse arbitrary intermediate wrapper nodes (<observers>, the event
     * name tag, <events>, the area scope) without having to safelist their
     * names. Stops naturally at any ancestor that holds other config
     * (e.g. <global> stays because it usually has <models>/<helpers>).
     */
    protected function detachAndPrune(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }
        $this->removeNodeWithLeadingWhitespace($node);

        $cur = $parent;
        while ($cur instanceof DOMElement) {
            // Don't prune the document element itself
            if (!($cur->parentNode instanceof DOMElement)) {
                break;
            }
            if ($this->elementHasChildElements($cur)) {
                break;
            }
            $next = $cur->parentNode;
            $this->removeNodeWithLeadingWhitespace($cur);
            $cur = $next;
        }
    }

    private function removeNodeWithLeadingWhitespace(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }
        if ($node->previousSibling !== null
            && $node->previousSibling->nodeType === XML_TEXT_NODE
            && trim((string) $node->previousSibling->nodeValue) === ''
        ) {
            $parent->removeChild($node->previousSibling);
        }
        $parent->removeChild($node);
    }

    protected function saveConfigXml(DOMDocument $dom, string $path): void
    {
        $xml = $dom->saveXML();
        if ($xml === false) {
            return;
        }
        file_put_contents($path, $xml);
    }

    private function elementHasChildElements(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolves the file path for a fully-qualified class name via Reflection.
     * Returns null if the class is not autoloadable in the current process.
     */
    protected function findClassFile(string $className): ?string
    {
        if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
            return null;
        }
        try {
            $file = (new ReflectionClass($className))->getFileName();
        } catch (\ReflectionException) {
            return null;
        }
        return $file === false ? null : $file;
    }

    /**
     * Inserts the given attribute line directly above the target method declaration.
     * Returns true on success, false if the method was not found.
     *
     * - Indentation matches the method declaration's leading whitespace.
     * - If the attribute (exact-match string) is already present anywhere immediately
     *   above the method declaration, the call is a no-op and returns true.
     * - Existing attributes on the method are preserved; the new one is inserted
     *   above them (but below the method's docblock if present).
     */
    protected function insertMethodAttribute(string $filePath, string $methodName, string $attributeLine): bool
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return false;
        }

        $lines = explode("\n", $contents);
        $methodLineIndex = $this->findMethodLine($lines, $methodName);
        if ($methodLineIndex === null) {
            return false;
        }

        // Walk up past existing attribute lines to find the insertion point
        $insertAt = $methodLineIndex;
        while ($insertAt > 0 && $this->isAttributeLine($lines[$insertAt - 1])) {
            $insertAt--;
        }

        // Idempotency: if the same attribute is already present in the attribute block, skip
        for ($i = $insertAt; $i < $methodLineIndex; $i++) {
            if (trim($lines[$i]) === trim($attributeLine)) {
                return true;
            }
        }

        $indent = $this->getLeadingWhitespace($lines[$methodLineIndex]);
        $newLine = $indent . trim($attributeLine);

        array_splice($lines, $insertAt, 0, [$newLine]);
        file_put_contents($filePath, implode("\n", $lines));
        return true;
    }

    /**
     * @param list<string> $lines
     */
    private function findMethodLine(array $lines, string $methodName): ?int
    {
        $pattern = '/^\s*(?:public|protected|private)?\s*(?:static\s+)?function\s+' . preg_quote($methodName, '/') . '\s*\(/';
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                return $i;
            }
        }
        return null;
    }

    private function isAttributeLine(string $line): bool
    {
        $trimmed = ltrim($line);
        return str_starts_with($trimmed, '#[');
    }

    private function getLeadingWhitespace(string $line): string
    {
        return preg_match('/^(\s*)/', $line, $m) ? $m[1] : '';
    }
}
