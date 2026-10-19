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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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

        $runner = Mage::getModel('index/runner');
        $queue = $runner->buildQueue();
        $lanes = $runner->buildLanes($queue);
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
        foreach ($queue as $process) {
            $output->write("Reindexing {$process->getIndexerCode()}... ");
            [$duration, $error] = $this->reindex($process);
            if ($error === null) {
                $output->writeln(sprintf('<info>done!</info> (%.2fs)', $duration));
            } else {
                $output->writeln(sprintf('<error>failed</error> (%.2fs): %s', $duration, $error));
                $failed++;
            }
        }
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

        $indexer = Mage::getSingleton('index/indexer');
        $result = [];
        foreach ($codes as $code) {
            $process = $indexer->getProcessByCode((string) $code);
            if (!$process) {
                $result[$code] = ['duration' => 0.0, 'error' => 'Index not found'];
                continue;
            }
            [$duration, $error] = $this->reindex($process);
            $result[$code] = ['duration' => $duration, 'error' => $error];
        }
        $output->writeln(self::JOB_RESULT_PREFIX . Mage::helper('core')->jsonEncode($result), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /**
     * Only a Mage_Core_Exception message is written for a human. Any other message can hold
     * SQL or paths, so it goes to exception.log, as in Mage_Index_Model_Runner::run().
     *
     * @return array{float, ?string} the duration and the error message
     */
    private function reindex(Mage_Index_Model_Process $process): array
    {
        $startTime = microtime(true);
        try {
            $process->reindexEverything();
            return [microtime(true) - $startTime, null];
        } catch (Mage_Core_Exception $e) {
            return [microtime(true) - $startTime, $e->getMessage()];
        } catch (Throwable $e) {
            Mage::logException($e);
            return [microtime(true) - $startTime, Mage::helper('index')->__('There was a problem with reindexing process.')];
        }
    }
}
