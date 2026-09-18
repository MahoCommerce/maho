<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho_AccessibilityScan_Model_Scan;
use Maho_AccessibilityScan_Model_Violation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'accessibility:scan',
    description: 'Scan a URL for WCAG violations with Playwright + axe-core',
)]
class AccessibilityScan extends BaseMahoCommand
{
    #[\Override]
    public function isEnabled(): bool
    {
        return $this->isModuleActive('Maho_AccessibilityScan');
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'URL to scan')]
        ?string $url = null,
        #[Option(description: 'WCAG conformance level (A, AA, AAA)')]
        ?string $level = null,
        #[Option(description: 'Maximum number of violations for exit code 0')]
        ?int $threshold = null,
        #[Option(description: 'Output format (table, json)')]
        string $format = 'table',
        #[Option(description: 'Force a reinstall of the shared browser runtime')]
        bool $reinstallPlaywright = false,
    ): int {
        $this->initMaho();

        $url = (string) $url;
        if ($url === '' || !Mage::helper('core')->isValidUrl($url)) {
            $output->writeln('<error>Please provide a valid URL with --url</error>');
            return 2;
        }

        $helper = Mage::helper('accessibilityscan');
        $storeId = $helper->resolveScanUrlStoreId($url);
        if ($storeId === null) {
            $output->writeln('<error>The URL to scan must belong to one of the configured store base URLs</error>');
            return 2;
        }
        $level = $helper->normalizeWcagLevel($level ?? $helper->getDefaultWcagLevel());

        $scan = Mage::getModel('accessibilityscan/scan');
        $scan->setUrl($url)
            ->setStoreId($storeId)
            ->setWcagLevel($level)
            ->setTriggeredBy(Maho_AccessibilityScan_Model_Scan::TRIGGER_CLI)
            ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING)
            ->save();

        if ($format !== 'json') {
            $output->writeln(sprintf('Scanning <comment>%s</comment> (WCAG %s)...', $url, $level));
        }

        $runner = Mage::getModel('accessibilityscan/runner');
        $runner->run($scan, $reinstallPlaywright);

        if ($scan->isFailed()) {
            if ($format === 'json') {
                $output->writeln((string) json_encode([
                    'scan_id' => (int) $scan->getId(),
                    'status' => $scan->getStatus(),
                    'error' => $scan->getErrorMessage(),
                ], JSON_PRETTY_PRINT));
            } else {
                $output->writeln('<error>Scan failed: ' . $scan->getErrorMessage() . '</error>');
            }
            return 2;
        }

        // Flatten grouped violations so both outputs list them by severity,
        // consistent with the admin UI and PDF report
        $violations = [];
        foreach ($scan->getViolationsGroupedByImpact() as $group) {
            foreach ($group as $violation) {
                $violations[] = $violation;
            }
        }

        if ($format === 'json') {
            $this->outputJson($output, $scan, array_map(
                fn(Maho_AccessibilityScan_Model_Violation $violation) => array_merge(
                    $violation->getData(),
                    ['viewports' => $violation->getViewports()],
                ),
                $violations,
            ));
        } else {
            $this->outputTable($output, $scan, $violations);
        }

        if ($threshold !== null && $scan->getTotalViolations() > $threshold) {
            if ($format !== 'json') {
                $output->writeln(sprintf(
                    '<error>Threshold exceeded: %d violations found, %d allowed</error>',
                    $scan->getTotalViolations(),
                    $threshold,
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
            'incomplete_count' => $scan->getIncompleteCount(),
            'violations' => array_values($violations),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param list<Maho_AccessibilityScan_Model_Violation> $violations
     */
    private function outputTable(
        OutputInterface $output,
        Maho_AccessibilityScan_Model_Scan $scan,
        array $violations,
    ): void {
        $rows = [];
        foreach ($violations as $violation) {
            $rows[] = [
                $violation->getAxeRuleId(),
                $violation->getImpact(),
                implode(', ', $violation->getViewports()) ?: '-',
                $violation->getWcagCriteria() ?: '-',
                mb_substr((string) $violation->getCssSelector(), 0, 60),
                $violation->getTemplateFile()
                    ? $violation->getTemplateFile() . ($violation->getTemplateLine() ? ':' . $violation->getTemplateLine() : '')
                    : '-',
            ];
        }

        if ($rows === []) {
            $output->writeln('<info>No automatically detectable violations found. Automated checks cover only part of WCAG, so manual testing is still recommended.</info>');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Rule', 'Impact', 'Viewports', 'WCAG', 'Selector', 'Template']);
            $table->setRows($rows);
            $table->render();
        }

        $counts = $scan->getViolationCounts();
        $output->writeln(sprintf(
            'Total: <comment>%d</comment> (critical: %d, serious: %d, moderate: %d, minor: %d), needs manual review: %d',
            $scan->getTotalViolations(),
            $counts['critical'],
            $counts['serious'],
            $counts['moderate'],
            $counts['minor'],
            $scan->getIncompleteCount(),
        ));
    }
}
