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
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'report:explain',
    description: 'Print the SQL of each statement of a report explorer report, with the time it took and its row count',
)]
class ReportExplain extends BaseMahoCommand
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
    ): int {
        $this->initMaho();
        try {
            $reportRequest = $this->reportRequest($preset, $saved, $request, ['range' => $range, 'from' => $from, 'to' => $to, 'compare' => $compare]);
            $statements = Mage::getModel('reports/explorer_runner')->explain($reportRequest, \Mage_Reports_Model_Explorer_Access::full());
        } catch (\Mage_Core_Exception $e) {
            $this->writeReportError($output, $e);
            return Command::FAILURE;
        }

        foreach ($statements as $statement) {
            $output->writeln(sprintf('<info>-- %s: %s ms, %d rows</info>', $statement['label'], $statement['milliseconds'], $statement['rows']));
            $output->writeln($statement['sql'] . ';');
            $output->writeln('');
        }
        return Command::SUCCESS;
    }
}
