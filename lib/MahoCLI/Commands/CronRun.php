<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Cron_Model_Observer;
use Mage_Cron_Model_Schedule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Maho\Db\Adapter\Pdo\Mysql;

#[AsCommand(
    name: 'cron:run',
    description: 'Run a group of cron processes (default/always) or a single job_code (eg: newsletter_send_all)',
)]
class CronRun extends BaseMahoCommand
{
    protected bool $isShellAvailable;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::REQUIRED, '"default" or "always"');
        $this->setShellAvailable();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $modeOrJobCode = $input->getArgument('mode');

        // Does the user want to run the default system cron jobs?
        $mode = $modeOrJobCode;
        $availableModes = ['default', 'always'];
        if (in_array($mode, $availableModes)) {
            if ($this->isProcessRunning("maho cron:run $mode")) {
                $output->writeln("<error>{$mode} is already running</error>");
                return Command::INVALID;
            }

            Mage::getConfig()->init()->loadEventObservers('crontab');
            Mage::app()->addEventArea('crontab');
            Mage::dispatchEvent($mode);
            return Command::SUCCESS;
        }

        // If the job is in the cron_schedule table, we execute it and "burn" that record
        $jobCode = $modeOrJobCode;
        $jobConfig = $this->getJobConfig($jobCode);
        if (!$jobConfig) {
            $output->writeln("<error>Unknown mode/job: {$modeOrJobCode}</error>");
            return Command::FAILURE;
        }
        $runConfig = $jobConfig['run'];
        if (!$runConfig['model']) {
            $output->writeln("<error>Invalid model definition for {$jobCode}</error>");
            return Command::FAILURE;
        }
        if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, (string) $runConfig['model'], $run)) {
            $output->writeln('<error>Invalid model/method definition, expecting "model/class::method"</error>');
            return Command::FAILURE;
        }
        if (!($model = Mage::getModel($run[1])) || !method_exists($model, $run[2])) {
            $output->writeln("<error>Invalid callback: {$run[1]}::{$run[2]} does not exist</error>");
            return Command::FAILURE;
        }

        /** @var Mage_Cron_Model_Schedule $schedule */
        $schedule = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_PENDING)
            ->addFieldToFilter('job_code', $jobCode)
            ->orderByScheduledAt()
            ->getFirstItem();
        if (!$schedule->getId()) {
            $schedule = false;
        }

        if ($schedule) {
            if (!$schedule->tryLockJob()) {
                $output->writeln("<error>{$jobCode} is already running</error>");
                return Command::INVALID;
            }

            $schedule
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
                ->setExecutedAt(date(\Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT))
                ->save();
        }

        try {
            $callback = [$model, $run[2]];
            call_user_func($callback, $schedule);
        } catch (\Exception $e) {
            if ($schedule) {
                $schedule
                    ->setFinishedAt(date(\Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT))
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                    ->setMessages($e->__toString())
                    ->save();
            }

            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if ($schedule) {
            $schedule
                ->setStatus(Mage_Cron_Model_Schedule::STATUS_SUCCESS)
                ->setFinishedAt(date(\Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT))
                ->save();
        }

        $output->writeln("<info>{$jobCode} executed successfully</info>");
        return Command::SUCCESS;
    }

    protected function getJobConfig(string $jobCode): array|false
    {
        $jobConfig = Mage::getConfig()->getNode("crontab/jobs/{$jobCode}")->asArray();
        if ($jobConfig) {
            return $jobConfig;
        }

        $jobConfig = Mage::getConfig()->getNode("default/crontab/jobs/{$jobCode}")->asArray();
        if ($jobConfig) {
            return $jobConfig;
        }

        return false;
    }

    protected function setShellAvailable(): void
    {
        $disabledFuncs = array_map('trim', preg_split("/,|\s+/", strtolower(ini_get('disable_functions'))));
        $isWinOS = !str_contains(strtolower(PHP_OS), 'darwin') && str_contains(strtolower(PHP_OS), 'win');
        $isShellDisabled = in_array('shell_exec', $disabledFuncs) || $isWinOS
            || !shell_exec('which expr 2>/dev/null')
            || !shell_exec('which ps 2>/dev/null')
            || !shell_exec('which sed 2>/dev/null');

        $this->isShellAvailable = !$isShellDisabled;
    }

    protected function isProcessRunning(string $command): bool
    {
        if (!$this->isShellAvailable) {
            return false;
        }

        $output = [];
        exec("ps auxwww | grep \"$command\" | grep -v grep | grep -v cron.php", $output);
        return count($output) > 1;
    }
}
