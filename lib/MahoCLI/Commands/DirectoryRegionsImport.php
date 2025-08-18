<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use DOMDocument;
use DOMElement;
use DOMNode;
use Mage;
use Mage_Core_Model_Resource_Db_Abstract;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'directory:regions:import',
    description: 'Import states/provinces for a country from CLDR/Unicode dataset',
)]
class DirectoryRegionsImport extends BaseMahoCommand
{
    private const CLDR_BASE_URL = 'https://raw.githubusercontent.com/unicode-org/cldr/main/common/subdivisions/';

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('country', 'c', InputOption::VALUE_REQUIRED, 'ISO-2 country code (e.g., US, IT, CA)')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Preview changes without importing')
            ->addOption('update-existing', 'u', InputOption::VALUE_NONE, 'Update existing regions (default: skip)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $countryCode = strtoupper($input->getOption('country'));
        $dryRun = $input->getOption('dry-run');
        $updateExisting = $input->getOption('update-existing');

        if (!$countryCode) {
            $output->writeln('<error>Country code is required. Use --country=XX</error>');
            return Command::FAILURE;
        }

        // Validate country exists
        $country = Mage::getModel('directory/country')->loadByCode($countryCode);
        if (!$country->getId()) {
            $output->writeln("<error>Country code '$countryCode' not found in database</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Importing regions for {$country->getName()} ($countryCode)</info>");
        
        if ($dryRun) {
            $output->writeln('<comment>DRY RUN MODE - No changes will be made</comment>');
        }

        // Fetch and parse subdivisions from English CLDR data
        $httpClient = HttpClient::create(['timeout' => 30]);
        $output->writeln("Fetching CLDR subdivision data...");
        
        try {
            $url = self::CLDR_BASE_URL . 'en.xml';
            $response = $httpClient->request('GET', $url);
            $xmlContent = $response->getContent();
            
            $subdivisions = $this->parseSubdivisions($xmlContent, $countryCode, $output);
            if (empty($subdivisions)) {
                $output->writeln("<comment>No regions found for $countryCode</comment>");
                return Command::SUCCESS;
            }
            
            $output->writeln("  Found " . count($subdivisions) . " regions");
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to fetch CLDR data: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
        
        // Process imports
        $output->writeln("\n<info>Processing " . count($subdivisions) . " regions...</info>");
        
        if (!$dryRun) {
            $progressBar = new ProgressBar($output, count($subdivisions));
            $progressBar->start();
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        $importRecords = [];
        $updateRecords = [];
        $skipRecords = [];
        
        /** @var Mage_Core_Model_Resource_Db_Abstract $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        
        foreach ($subdivisions as $code => $defaultName) {
            if (!$dryRun) {
                $progressBar->advance();
            }
            
            // Check if region already exists
            $existingRegion = Mage::getModel('directory/region')
                ->loadByCode($code, $countryCode);
            
            if ($existingRegion->getId()) {
                if (!$updateExisting) {
                    $skipRecords[] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingRegion->getDefaultName()];
                    $skipped++;
                    continue;
                }
                
                // Update existing region
                if (!$dryRun) {
                    $existingRegion->setDefaultName($defaultName);
                    $existingRegion->save();
                } else {
                    $updateRecords[] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingRegion->getDefaultName()];
                }
                $updated++;
            } else {
                // Insert new region
                if (!$dryRun) {
                    $region = Mage::getModel('directory/region');
                    $region->setCountryId($countryCode);
                    $region->setCode($code);
                    $region->setDefaultName($defaultName);
                    $region->save();
                } else {
                    $importRecords[] = ['code' => $code, 'name' => $defaultName];
                }
                $imported++;
            }
        }
        
        if (!$dryRun) {
            $progressBar->finish();
            $output->writeln('');
            
            // Clear caches
            $output->writeln('Clearing caches...');
            Mage::app()->getCacheInstance()->cleanType('config');
            Mage::app()->getCacheInstance()->cleanType('block_html');
        }
        
        // Summary
        $output->writeln("\n<info>Import Summary:</info>");
        $table = new Table($output);
        $table->setHeaders(['Action', 'Count']);
        $table->addRow(['Imported', $imported]);
        $table->addRow(['Updated', $updated]);
        $table->addRow(['Skipped', $skipped]);
        $table->addRow(['<info>Total</info>', '<info>' . ($imported + $updated + $skipped) . '</info>']);
        $table->render();
        
        if ($dryRun) {
            // Show details of what would be imported/updated/skipped
            if (!empty($importRecords)) {
                $output->writeln("\n<info>Regions to be imported:</info>");
                $table = new Table($output);
                $table->setHeaders(['Code', 'Name']);
                foreach ($importRecords as $record) {
                    $table->addRow([$record['code'], $record['name']]);
                }
                $table->render();
            }
            
            if (!empty($updateRecords)) {
                $output->writeln("\n<info>Regions to be updated:</info>");
                $table = new Table($output);
                $table->setHeaders(['Code', 'Current Name', 'New Name']);
                foreach ($updateRecords as $record) {
                    $table->addRow([$record['code'], $record['existing'], $record['name']]);
                }
                $table->render();
            }
            
            if (!empty($skipRecords) && $output->isVerbose()) {
                $output->writeln("\n<info>Regions to be skipped (already exist):</info>");
                $table = new Table($output);
                $table->setHeaders(['Code', 'Name']);
                foreach ($skipRecords as $record) {
                    $table->addRow([$record['code'], $record['existing']]);
                }
                $table->render();
            }
            
            $output->writeln("\n<comment>This was a dry run. Use without --dry-run to apply changes.</comment>");
        }

        return Command::SUCCESS;
    }

    private function parseSubdivisions(string $xmlContent, string $countryCode, OutputInterface $output): array
    {
        $allSubdivisions = [];
        $leafSubdivisions = [];
        $countryPrefix = strtolower($countryCode);
        $hasLeafNodes = false;
        
        // Split into lines and process each
        $lines = explode("\n", $xmlContent);
        foreach ($lines as $line) {
            // Match subdivision elements for this country
            if (preg_match('/<subdivision\s+type="(' . preg_quote($countryPrefix) . '[^"]+)">([^<]+)<\/subdivision>/', $line, $match)) {
                $type = $match[1];
                $name = trim($match[2]);
                $code = strtoupper(substr($type, strlen($countryPrefix)));
                
                // Check if line contains a comment
                if (str_contains($line, '<!--')) {
                    // Extract comment content
                    if (preg_match('/<!--(.*)-->/', $line, $commentMatch)) {
                        $comment = trim($commentMatch[1]);
                        
                        // Skip deprecated subdivisions
                        if (str_contains($comment, 'deprecated')) {
                            continue;
                        }
                        
                        // Check if it's a leaf node (has "in " in comment indicating parent)
                        if (str_contains($comment, 'in ')) {
                            $leafSubdivisions[$code] = $name;
                            $hasLeafNodes = true;
                            continue;
                        }
                    }
                }
                
                // If we get here, it's not a leaf node (no comment or no "in" in comment)
                $allSubdivisions[$code] = $name;
            }
        }
        
        // Debug output
        if ($output->isVerbose()) {
            $output->writeln("  Debug: Found " . count($leafSubdivisions) . " leaf subdivisions");
            $output->writeln("  Debug: Found " . count($allSubdivisions) . " non-leaf subdivisions");
            $output->writeln("  Debug: Has leaf nodes: " . ($hasLeafNodes ? 'yes' : 'no'));
        }
        
        // If there are leaf nodes (provinces/counties), return only those
        // Otherwise return all subdivisions (for countries without hierarchy)
        return $hasLeafNodes ? $leafSubdivisions : $allSubdivisions;
    }
}