<?php

namespace Maho\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cron:run',
    description: 'Run a group of cron processes (available groups: default/always)'
)]
class CronRun extends BaseMahoCommand
{
    protected bool $isShellAvailable;

    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::REQUIRED, '"default" or "always"');
        $this->setShellAvailable();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getArgument('mode');
        $availableModes = ['default', 'always'];
        if (!in_array($mode, $availableModes)) {
            $output->writeln("<error>Invalid mode: $mode</error>");
            return Command::FAILURE;
        }

        if (!$this->isProcessRunning("maho cron:run $mode")) {
            $this->initMaho();
            Mage::getConfig()->init()->loadEventObservers('crontab');
            Mage::app()->addEventArea('crontab');
            Mage::dispatchEvent($mode);
        }

        return Command::SUCCESS;
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

    protected function isProcessRunning($command)
    {
        if (!$this->isShellAvailable) {
            return false;
        }

        $output = [];
        exec("ps auxwww | grep \"$command\" | grep -v grep | grep -v cron.php", $output);
        return count($output) > 1;
    }
}
