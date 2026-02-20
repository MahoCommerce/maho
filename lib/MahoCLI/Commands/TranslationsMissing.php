<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'translations:missing',
    description: 'Display used translations strings that are missing from csv files',
)]
class TranslationsMissing extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('lang', InputArgument::OPTIONAL, 'Specify which language pack to check in app/locale, default is en_US', 'en_US');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $lang = $input->getArgument('lang');
        $definedFileMap = $this->getDefinedStrings($lang);
        $usedFileMap = $this->getUsedStrings();

        $definedFlat = array_unique(array_merge(...array_values($definedFileMap)));
        $usedFlat = array_unique(array_merge(...array_values($usedFileMap)));

        foreach ($usedFileMap as $file => $used) {
            $missing = array_diff($used, $definedFlat);
            if (count($missing)) {
                echo "$file\n    " . implode("\n    ", $missing) . "\n\n";
            }
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
        $files = [];
        $fh = fopen('php://stdin', 'r');

        if ($fh === false) {
            return $files;
        }

        stream_set_blocking($fh, false);

        while (($line = fgets($fh)) !== false) {
            $files[] = $line;
        }

        if (count($files) === 0) {
            $files = array_merge(
                // Grep for all files that might call the __ function
                explode("\n", (string) shell_exec("grep -Frl --exclude-dir='.git' --include=*.php --include=*.phtml '__' .")),
                // Grep for all XML files that might use the translate attribute
                explode("\n", (string) shell_exec("grep -Frl --exclude-dir='.git' --include=*.xml 'translate=' .")),
            );
        }

        return array_filter(array_map('trim', $files));
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
        $map = [];
        $files = $this->getFiles();
        foreach ($files as $file) {
            // Skip files that don't exist (e.g., deleted files in a PR)
            if (!file_exists($file)) {
                continue;
            }

            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $contents = file_get_contents($file);

            if ($contents === false) {
                echo "ERROR: File not found $file\n";
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
                $xml = new \SimpleXMLElement($contents);
                // Get all nodes with translate="" attribute
                $nodes = $xml->xpath('//*[@translate]');
                foreach ($nodes as $node) {
                    // Which children should we translate?
                    $translateNode = $node['translate'];
                    if (!$translateNode instanceof \SimpleXMLElement) {
                        continue;
                    }
                    $translateChildren = array_map('trim', explode(' ', $translateNode->__toString()));
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
}
