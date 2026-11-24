<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'report:run',
    description: 'Run a report of the report explorer: a preset, a saved report or a JSON request',
)]
class ReportRun extends BaseMahoCommand
{
    use ReportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'The code of a preset, such as sales_overview')]
        ?string $preset = null,
        #[Option(description: 'The ID of a saved report')]
        ?int $saved = null,
        #[Option(description: 'A request as JSON, such as {"dataset":"sales_orders","metrics":["orders"]}')]
        ?string $request = null,
        #[Option(description: 'A named period, such as last_30_days or last_month')]
        ?string $range = null,
        #[Option(description: 'The first local day, YYYY-MM-DD')]
        ?string $from = null,
        #[Option(description: 'The last local day, YYYY-MM-DD')]
        ?string $to = null,
        #[Option(description: 'previous_period or previous_year')]
        ?string $compare = null,
        #[Option(description: 'The rows of the table and of the JSON, not of the CSV')]
        ?int $limit = null,
        #[Option(description: 'table, csv or json')]
        string $format = 'table',
    ): int {
        $this->initMaho();
        if (!in_array($format, ['table', 'csv', 'json'], true)) {
            $output->writeln('<error>The --format option must be table, csv or json.</error>');
            return Command::INVALID;
        }

        $access = \Mage_Reports_Model_Explorer_Access::full();
        try {
            $reportRequest = $this->reportRequest($preset, $saved, $request, ['range' => $range, 'from' => $from, 'to' => $to, 'compare' => $compare, 'limit' => $limit]);
            if ($format === 'csv') {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    throw new \RuntimeException('Cannot open the output stream.');
                }
                Mage::getModel('reports/explorer_csv')->write($handle, $reportRequest, $access, \Mage_Reports_Service_Explorer::exportMaxRows());
                fclose($handle);
                return Command::SUCCESS;
            }
            $result = Mage::getService('reports/explorer')->run($reportRequest, $access);
        } catch (\Mage_Core_Exception $e) {
            $this->writeReportError($output, $e);
            return Command::FAILURE;
        }

        if ($format === 'json') {
            $output->writeln(Mage::helper('core')->jsonEncode($result));
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(array_column($result['columns'], 'label'));
        foreach ($result['rows'] as $row) {
            $cells = [];
            foreach ($result['columns'] as $column) {
                $cells[] = $column['kind'] === 'dimension'
                    ? (string) ($row['labels'][$column['key']] ?? $row['dimensions'][$column['key']] ?? '')
                    : $this->formatValue($row['values'][$column['key']] ?? null);
            }
            $table->addRow($cells);
        }
        $output->writeln("{$result['dataset']} " . ($result['period'] ? "{$result['period']['from']} to {$result['period']['to']} ({$result['period']['timezone']})" : ''));
        $table->render();
        $output->writeln(sprintf('%d of %d rows', count($result['rows']), $result['totalRows']));
        foreach ($result['warnings'] as $warning) {
            $output->writeln("<comment>{$warning}</comment>");
        }
        return Command::SUCCESS;
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_float($value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'),
            default => (string) $value,
        };
    }
}
