<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho_AccessibilityScan_Model_Scan;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'accessibility:scan',
    description: 'Scan a URL for WCAG violations with Playwright + axe-core',
)]
class AccessibilityScan extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL to scan')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'WCAG conformance level (A, AA, AAA)')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Maximum number of violations for exit code 0')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store ID', '1')
            ->addOption('reinstall-playwright', null, InputOption::VALUE_NONE, 'Force a reinstall of Playwright and Chromium');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $url = (string) $input->getOption('url');
        if ($url === '' || !Mage::helper('core')->isValidUrl($url)) {
            $output->writeln('<error>Please provide a valid URL with --url</error>');
            return 2;
        }

        $helper = Mage::helper('accessibilityscan');
        $level = $helper->normalizeWcagLevel($input->getOption('level') ?? $helper->getDefaultWcagLevel());
        $format = (string) $input->getOption('format');

        $scan = Mage::getModel('accessibilityscan/scan');
        $scan->setUrl($url)
            ->setStoreId((int) $input->getOption('store'))
            ->setWcagLevel($level)
            ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING)
            ->save();

        if ($format !== 'json') {
            $output->writeln(sprintf('Scanning <comment>%s</comment> (WCAG %s)...', $url, $level));
        }

        $runner = Mage::getModel('accessibilityscan/runner');
        $runner->run($scan, (bool) $input->getOption('reinstall-playwright'));

        if ($scan->isFailed()) {
            if ($format === 'json') {
                $output->writeln((string) json_encode([
                    'scan_id' => (int) $scan->getId(),
                    'status' => $scan->getStatus(),
                    'error' => $scan->getData('error_message'),
                ], JSON_PRETTY_PRINT));
            } else {
                $output->writeln('<error>Scan failed: ' . $scan->getData('error_message') . '</error>');
            }
            return 2;
        }

        $violations = $scan->getViolationCollection()->setOrder('impact', 'ASC');

        if ($format === 'json') {
            $this->outputJson($output, $scan, $violations->toArray()['items'] ?? []);
        } else {
            $this->outputTable($output, $scan, $violations);
        }

        $threshold = $input->getOption('threshold');
        if ($threshold !== null && $scan->getTotalViolations() > (int) $threshold) {
            if ($format !== 'json') {
                $output->writeln(sprintf(
                    '<error>Threshold exceeded: %d violations found, %d allowed</error>',
                    $scan->getTotalViolations(),
                    (int) $threshold,
                ));
            }
            return 1;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     */
    private function outputJson(OutputInterface $output, Maho_AccessibilityScan_Model_Scan $scan, array $violations): void
    {
        $output->writeln((string) json_encode([
            'scan_id' => (int) $scan->getId(),
            'url' => $scan->getUrl(),
            'wcag_level' => $scan->getWcagLevel(),
            'status' => $scan->getStatus(),
            'total_violations' => $scan->getTotalViolations(),
            'violations_by_impact' => $scan->getViolationCounts(),
            'violations' => array_values($violations),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function outputTable(
        OutputInterface $output,
        Maho_AccessibilityScan_Model_Scan $scan,
        \Maho_AccessibilityScan_Model_Resource_Violation_Collection $violations,
    ): void {
        $rows = [];
        foreach ($violations as $violation) {
            $rows[] = [
                $violation->getAxeRuleId(),
                $violation->getImpact(),
                $violation->getWcagCriteria() ?: '-',
                mb_substr((string) $violation->getCssSelector(), 0, 60),
                $violation->getTemplateFile()
                    ? $violation->getTemplateFile() . ($violation->getTemplateLine() ? ':' . $violation->getTemplateLine() : '')
                    : '-',
            ];
        }

        if ($rows === []) {
            $output->writeln('<info>No violations found.</info>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Rule', 'Impact', 'WCAG', 'Selector', 'Template']);
            $table->setRows($rows);
            $table->render();
        }

        $counts = $scan->getViolationCounts();
        $output->writeln(sprintf(
            'Total: <comment>%d</comment> (critical: %d, serious: %d, moderate: %d, minor: %d)',
            $scan->getTotalViolations(),
            $counts['critical'],
            $counts['serious'],
            $counts['moderate'],
            $counts['minor'],
        ));
    }
}
