<?php

/**
 * Shared source scanning of the translation commands.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

trait TranslationScanTrait
{
    private bool $scanIncomplete = false;

    /**
     * Search the source files with grep. A failed search marks the scan as incomplete.
     *
     * @return array<int, string>
     */
    private function searchSourceFiles(): array
    {
        $files = [];
        foreach ([
            // Grep for all files that might call the __ function
            "grep -Frl --exclude-dir='.git' --include=*.php --include=*.phtml '__' .",
            // Grep for all XML files that might use the translate attribute
            "grep -Frl --exclude-dir='.git' --include=*.xml 'translate=' .",
        ] as $search) {
            $matches = [];
            $status = 0;
            $result = exec($search, $matches, $status);
            // Grep returns 1 for a complete search without matches.
            if ($result === false || ($status !== 0 && $status !== 1)) {
                $this->scanIncomplete = true;
                fwrite(STDERR, "WARNING: Translation source discovery failed (exit $status): $search\n");
            }
            $files = array_merge($files, $matches);
        }

        return $files;
    }

    /**
     * Parse an XML source file. A malformed file marks the scan as incomplete.
     */
    private function parseSourceXml(string $contents, string $file): ?\SimpleXMLElement
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            return new \SimpleXMLElement($contents);
        } catch (\Throwable $e) {
            $this->scanIncomplete = true;
            $parseErrors = libxml_get_errors();
            $detail = $parseErrors[0]->message ?? $e->getMessage();
            fwrite(STDERR, "WARNING: Skipping malformed translation XML $file: " . trim($detail) . "\n");
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}
