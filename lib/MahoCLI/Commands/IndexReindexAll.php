<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Mage_Index_Model_Process;
use Maho\Job\StepState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:reindex:all',
    description: 'Reindex all indexes',
)]
class IndexReindexAll extends BaseMahoCommand
{
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Reindex one index at a time. SQLite always does.')]
        bool $sequential = false,
        #[Option(description: 'Reindex the index codes that stdin lists as JSON. The parallel run uses it.', name: 'codes-from-stdin')]
        bool $codesFromStdin = false,
    ): int {
        $this->initMaho();

        if ($codesFromStdin) {
            return $this->reindexCodesFromStdin($input, $output);
        }

        $queue = $this->processService()->getQueue();
        $lanes = Mage::getModel('index/runner')->buildLanes($queue);
        $totalStartTime = microtime(true);

        if ($sequential || count($lanes) < 2 || !$this->canWriteInParallel()) {
            $failed = $this->reindexSequential($queue, $output);
        } else {
            $failed = $this->reindexParallel($lanes, $output);
        }

        $output->writeln('');
        if ($failed) {
            $output->writeln(sprintf('<error>%d of %d indexes failed</error>', $failed, count($queue)));
            return Command::FAILURE;
        }
        $output->writeln(sprintf('<info>All indexes reindexed in %.2fs</info>', microtime(true) - $totalStartTime));
        return Command::SUCCESS;
    }

    /**
     * SQLite allows one writer at a time, so a second process only waits.
     */
    private function canWriteInParallel(): bool
    {
        return !Mage::getSingleton('core/resource')->getConnection('core_write') instanceof \Maho\Db\Adapter\Pdo\Sqlite;
    }

    /**
     * @param Mage_Index_Model_Process[] $queue
     * @return int the number of indexes that failed
     */
    private function reindexSequential(array $queue, OutputInterface $output): int
    {
        $failed = 0;
        $this->processService()->reindexQueue(
            $queue,
            function (Mage_Index_Model_Process $process) use ($output): void {
                $output->write("Reindexing {$process->getIndexerCode()}... ");
            },
            function (Mage_Index_Model_Process $process, array $result) use ($output, &$failed): void {
                if ($result['state'] === StepState::Success) {
                    $output->writeln(sprintf('<info>done!</info> (%.2fs)', $result['duration']));
                } else {
                    $output->writeln(sprintf('<error>failed</error> (%.2fs): %s', $result['duration'], $result['message']));
                    $failed++;
                }
            },
        );
        return $failed;
    }

    /**
     * Start one process for each lane, and print the result of each index when its lane ends.
     *
     * @param list<list<Mage_Index_Model_Process>> $lanes
     * @return int the number of indexes that failed
     */
    private function reindexParallel(array $lanes, OutputInterface $output): int
    {
        $parts = array_map(
            fn(array $lane): array => array_map(fn(Mage_Index_Model_Process $process): string => $process->getIndexerCode(), $lane),
            $lanes,
        );
        $output->writeln(sprintf('Reindexing in %d parallel lanes: %s', count($parts), implode(' | ', array_map(fn(array $codes): string => implode(', ', $codes), $parts))));

        $failed = 0;
        $this->runJobs(
            new ProgressBar(new NullOutput()),
            $parts,
            ['index:reindex:all', '--codes-from-stdin'],
            function (array $result) use ($output, &$failed): void {
                foreach ($result as $code => $step) {
                    if ($step['error'] === null) {
                        $output->writeln(sprintf('Reindexing %s... <info>done!</info> (%.2fs)', $code, $step['duration']));
                    } else {
                        $output->writeln(sprintf('Reindexing %s... <error>failed</error> (%.2fs): %s', $code, $step['duration'], $step['error']));
                        $failed++;
                    }
                }
            },
            function (int $i, string $error) use ($output, $parts, &$failed): void {
                $output->writeln(sprintf('<error>The lane %s stopped: %s</error>', implode(', ', $parts[$i]), $error));
                $failed += count($parts[$i]);
            },
        );
        return $failed;
    }

    /**
     * Reindex the codes that stdin lists, in that order. Print the duration and the error of
     * each one as JSON on one result line.
     */
    private function reindexCodesFromStdin(InputInterface $input, OutputInterface $output): int
    {
        $stdin = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $codes = Mage::helper('core')->jsonDecode((string) stream_get_contents($stdin ?? STDIN));
        if (!is_array($codes)) {
            $output->writeln('<error>--codes-from-stdin needs a JSON list of index codes.</error>');
            return Command::FAILURE;
        }

        $result = [];
        foreach ($codes as $code) {
            try {
                $process = $this->processService()->getByCode((string) $code);
            } catch (Mage_Core_Exception $e) {
                $result[$code] = ['duration' => 0.0, 'error' => $e->getMessage()];
                continue;
            }
            $step = $this->processService()->reindexQueue([$process])[$process->getIndexerCode()];
            $result[$code] = ['duration' => $step['duration'], 'error' => $step['state'] === StepState::Success ? null : $step['message']];
        }
        $output->writeln(self::JOB_RESULT_PREFIX . Mage::helper('core')->jsonEncode($result), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    private function processService(): \Mage_Index_Service_Process
    {
        return Mage::getService('index/process');
    }
}
