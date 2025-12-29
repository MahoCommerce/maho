<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:connect',
    description: 'Opens the database command-line interface using credentials from your local.xml file',
)]
class DBConnect extends BaseMahoCommand
{
    use DatabaseCliTrait;

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $connConfig = Mage::getConfig()->getNode('global/resources/default_setup/connection');

        $engine = $this->getEngine($connConfig);

        return match ($engine) {
            'mysql' => $this->connectMysql($connConfig),
            'pgsql' => $this->connectPgsql($connConfig),
            'sqlite' => $this->connectSqlite($connConfig),
            default => $this->handleUnsupportedEngine($engine),
        };
    }

    private function connectMysql(mixed $connConfig): int
    {
        $host = (string) $connConfig->host;
        $dbname = (string) $connConfig->dbname;
        $user = (string) $connConfig->username;
        $password = (string) $connConfig->password;

        $configFile = $this->createTempMySQLConfig($host, $user, $password);

        $command = sprintf(
            'mysql --defaults-extra-file=%s %s',
            escapeshellarg($configFile),
            escapeshellarg($dbname),
        );

        return $this->runInteractiveCommand($command);
    }

    private function connectPgsql(mixed $connConfig): int
    {
        $host = (string) $connConfig->host;
        $dbname = (string) $connConfig->dbname;
        $user = (string) $connConfig->username;
        $password = (string) $connConfig->password;
        $port = empty($connConfig->port) ? '5432' : (string) $connConfig->port;

        $configFile = $this->createTempPgpassFile($host, $port, $dbname, $user, $password);

        $command = sprintf(
            'PGPASSFILE=%s psql -h %s -p %s -U %s -d %s',
            escapeshellarg($configFile),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($dbname),
        );

        return $this->runInteractiveCommand($command);
    }

    private function connectSqlite(mixed $connConfig): int
    {
        $dbPath = BP . DS . 'var' . DS . 'db' . DS . (string) $connConfig->dbname;

        $command = sprintf(
            'sqlite3 %s',
            escapeshellarg($dbPath),
        );

        return $this->runInteractiveCommand($command);
    }

    private function handleUnsupportedEngine(string $engine): int
    {
        fwrite(STDERR, "Unsupported database engine: {$engine}\n");
        return Command::FAILURE;
    }

    private function runInteractiveCommand(string $command): int
    {
        $descriptorspec = [
            0 => STDIN,
            1 => STDOUT,
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes);
        if (is_resource($process)) {
            stream_set_blocking($pipes[2], false);

            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(100000);
            }

            fclose($pipes[2]);
            proc_close($process);

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
