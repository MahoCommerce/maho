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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:query',
    description: 'Execute a SQL query using the database credentials from your local.xml file',
)]
class DBQuery extends BaseMahoCommand
{
    use DatabaseCliTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'query',
            InputArgument::REQUIRED,
            'The SQL query to execute',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $connConfig = Mage::getConfig()->getNode('global/resources/default_setup/connection');

        $engine = $this->getEngine($connConfig);
        $query = $input->getArgument('query');

        return match ($engine) {
            'mysql' => $this->executeMysql($connConfig, $query),
            'pgsql' => $this->executePgsql($connConfig, $query),
            'sqlite' => $this->executeSqlite($connConfig, $query),
            default => $this->handleUnsupportedEngine($engine),
        };
    }

    private function executeMysql(mixed $connConfig, string $query): int
    {
        $host = (string) $connConfig->host;
        $dbname = (string) $connConfig->dbname;
        $user = (string) $connConfig->username;
        $password = (string) $connConfig->password;

        $configFile = $this->createTempMySQLConfig($host, $user, $password);

        $command = sprintf(
            'mysql --defaults-extra-file=%s %s -e %s',
            escapeshellarg($configFile),
            escapeshellarg($dbname),
            escapeshellarg($query),
        );

        return $this->runCommand($command);
    }

    private function executePgsql(mixed $connConfig, string $query): int
    {
        $host = (string) $connConfig->host;
        $dbname = (string) $connConfig->dbname;
        $user = (string) $connConfig->username;
        $password = (string) $connConfig->password;
        $port = empty($connConfig->port) ? '5432' : (string) $connConfig->port;

        $configFile = $this->createTempPgpassFile($host, $port, $dbname, $user, $password);

        $command = sprintf(
            'PGPASSFILE=%s psql -h %s -p %s -U %s -d %s -c %s',
            escapeshellarg($configFile),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($dbname),
            escapeshellarg($query),
        );

        return $this->runCommand($command);
    }

    private function executeSqlite(mixed $connConfig, string $query): int
    {
        $dbPath = BP . DS . 'var' . DS . 'db' . DS . (string) $connConfig->dbname;

        $command = sprintf(
            'sqlite3 -header -column %s %s',
            escapeshellarg($dbPath),
            escapeshellarg($query),
        );

        return $this->runCommand($command);
    }

    private function handleUnsupportedEngine(string $engine): int
    {
        fwrite(STDERR, "Unsupported database engine: {$engine}\n");
        return Command::FAILURE;
    }

    private function runCommand(string $command): int
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

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0 && $stderr) {
                fwrite(STDERR, $stderr);
            }

            return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
        }

        return Command::FAILURE;
    }
}
