<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dev:translations:unused',
    description: 'Display defined translations strings that are not used in templates',
)]
class TranslationsUnused extends BaseMahoCommand
{
    private bool $scanIncomplete = false;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Specify which language pack to check in app/locale, default is en_US')]
        string $lang = 'en_US',
        #[Option(description: 'Remove unused translation lines from CSV files')]
        bool $remove = false,
    ): int {
        $this->initMaho();

        $definedFileMap = $this->getDefinedStrings($lang);
        $usedFileMap = $this->getUsedStrings();

        if ($this->scanIncomplete) {
            fwrite(STDERR, "ERROR: Translation scan incomplete; no translations were removed. Review the warnings above.\n");
            return Command::FAILURE;
        }

        $usedFlat = array_unique(array_merge(...array_values($usedFileMap)));

        $totalRemoved = 0;
        foreach ($definedFileMap as $file => $defined) {
            $unused = array_diff($defined, $usedFlat);
            if (count($unused)) {
                echo "$file\n    " . implode("\n    ", $unused) . "\n\n";

                if ($remove) {
                    $removedCount = $this->removeUnusedFromFile($file, $unused);
                    if ($removedCount === false) {
                        $output->writeln("<error>Failed to update translations in $file</error>");
                        return Command::FAILURE;
                    }
                    $totalRemoved += $removedCount;
                    $output->writeln("Removed $removedCount unused translations from $file");
                }
            }
        }

        if ($remove && $totalRemoved > 0) {
            $output->writeln("Total removed: $totalRemoved unused translation lines");
        }

        return Command::SUCCESS;
    }

    /**
     * Get a list of files to scan for translated strings
     *
     * @return array<int, string>
     */
    protected function getFiles(): array
    {
        $files = array_merge(
            // Grep for all files that might call the __ function
            explode("\n", (string) shell_exec("grep -Frl --exclude-dir='.git' --include=*.php --include=*.phtml '__' .")),
            // Grep for all XML files that might use the translate attribute
            explode("\n", (string) shell_exec("grep -Frl --exclude-dir='.git' --include=*.xml 'translate=' .")),
        );
        return array_filter(array_map(trim(...), $files));
    }

    /**
     * Get all defined translation strings per file from app/locale/$CODE/*.csv
     *
     * @return array<string, array<int, string>>
     */
    protected function getDefinedStrings(string $lang): array
    {
        $map = [];

        $files = glob("app/locale/$lang/*.csv");
        if (!is_array($files)) {
            return $map;
        }

        $parser = new \Maho\File\Csv();
        $parser->setDelimiter(',');
        foreach ($files as $file) {
            $data = $parser->getDataPairs($file);
            $map[$file] = array_keys($data);
        }

        return $map;
    }

    /**
     * Get all used translation strings per file from all php, phtml, and xml files
     *
     * @return array<string, array<int, string>>
     */
    protected function getUsedStrings(): array
    {
        $this->scanIncomplete = false;
        $map = [];
        $files = $this->getFiles();
        foreach ($files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $contents = file_get_contents($file);

            if ($contents === false) {
                echo "ERROR: File not found $file\n";
                $this->scanIncomplete = true;
                continue;
            }

            $matches = [];

            if ($ext === 'php' || $ext === 'phtml') {
                // Regex to get first argument of __ function
                // https://stackoverflow.com/a/5696141
                $re_dq = '/__\s*\(\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*\s*)"/s';
                $re_sq = "/__\s*\(\s*'([^'\\\\]*(?:\\\\.[^'\\\\]*)*\s*)'/s";

                if (preg_match_all($re_dq, $contents, $_matches)) {
                    $matches = array_merge($matches, str_replace('\"', '"', $_matches[1]));
                }
                if (preg_match_all($re_sq, $contents, $_matches)) {
                    $matches = array_merge($matches, str_replace("\'", "'", $_matches[1]));
                }
            } elseif ($ext === 'xml') {
                $previousErrorMode = libxml_use_internal_errors(true);
                libxml_clear_errors();
                try {
                    $xml = new \SimpleXMLElement($contents);
                } catch (\Throwable $e) {
                    $this->scanIncomplete = true;
                    $parseErrors = libxml_get_errors();
                    $detail = $parseErrors[0]->message ?? $e->getMessage();
                    fwrite(STDERR, "WARNING: Skipping malformed translation XML $file: " . trim($detail) . "\n");
                    continue;
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previousErrorMode);
                }
                // Get all nodes with translate="" attribute
                $nodes = $xml->xpath('//*[@translate]');
                foreach ($nodes as $node) {
                    // Which children should we translate?
                    $translateNode = $node['translate'];
                    if (!$translateNode instanceof \SimpleXMLElement) {
                        continue;
                    }
                    $translateChildren = array_map(trim(...), explode(' ', $translateNode->__toString()));
                    foreach ($node->children() as $child) {
                        if (in_array($child->getName(), $translateChildren)) {
                            $matches[] = $child->__toString();
                        }
                    }
                }
            }

            $matches = array_filter(array_unique($matches));
            if (count($matches)) {
                $map[$file] = $matches;
            }
        }
        return $map;
    }

    /**
     * Remove unused translation strings from a CSV file
     *
     * @param array<int, string> $unused
     * @return int|false Number of removed records, or false when the CSV could not be read or written fully
     */
    protected function removeUnusedFromFile(string $file, array $unused): int|false
    {
        if (!file_exists($file)) {
            return false;
        }

        $csvData = file_get_contents($file);
        if ($csvData === false) {
            return false;
        }

        $unusedSet = array_fill_keys($unused, true);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return false;
        }
        if (fwrite($stream, $csvData) !== strlen($csvData)) {
            fclose($stream);
            return false;
        }
        rewind($stream);

        $updatedCsv = '';
        $removedCount = 0;
        $recordStart = 0;
        while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            $recordEnd = ftell($stream);
            $record = substr($csvData, $recordStart, $recordEnd - $recordStart);
            $recordStart = $recordEnd;

            if (array_key_exists(1, $row) && isset($row[0]) && isset($unusedSet[$row[0]])) {
                $removedCount++;
                continue;
            }

            $updatedCsv .= $record;
        }
        fclose($stream);

        if ($removedCount > 0) {
            $writtenBytes = $this->writeCsvFile($file, $updatedCsv);
            if ($writtenBytes === false || $writtenBytes !== strlen($updatedCsv)) {
                return false;
            }
        }
        return $removedCount;
    }

    protected function writeCsvFile(string $file, string $contents): int|false
    {
        return file_put_contents($file, $contents);
    }
}
