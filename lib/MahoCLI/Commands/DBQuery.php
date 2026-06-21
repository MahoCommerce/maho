<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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
        $this->addOption(
            'driver',
            null,
            InputOption::VALUE_REQUIRED,
            "Execution backend: 'auto' (native client when available, otherwise the framework "
                . "database connection), 'client' (force the native client), or 'adapter' (force "
                . 'the framework connection). The adapter path runs a single statement and may '
                . 'misread an unquoted "?" (e.g. a PostgreSQL JSON operator) as a bind '
                . 'placeholder; use --driver=client for those.',
            'auto',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $connConfig = Mage::getConfig()->getNode('global/resources/default_setup/connection');

        $engine = $this->getEngine($connConfig);
        $query = (string) $input->getArgument('query');

        $driver = (string) $input->getOption('driver');
        if (!in_array($driver, ['auto', 'client', 'adapter'], true)) {
            $this->writeError($output, "Invalid --driver value: {$driver} (expected: auto, client, or adapter)");
            return Command::INVALID;
        }

        // 'adapter' forces the framework connection; 'client' forces the native CLI client;
        // 'auto' uses the client when its binary is available and otherwise falls back to the
        // framework connection so the command still works on PHP-only hosts (CI, slim images).
        $useAdapter = match ($driver) {
            'adapter' => true,
            'client' => false,
            default => !$this->isClientBinaryAvailable($engine),
        };

        if ($useAdapter) {
            if ($driver === 'auto') {
                $binary = $this->clientBinaryForEngine($engine);
                $this->writeError($output, sprintf(
                    "Notice: '%s' client binary not found; running the query through the framework database connection.",
                    $binary !== '' ? $binary : $engine,
                ));
            }
            return $this->executeViaAdapter($output, $query, $this->isClientBinaryAvailable($engine));
        }

        return match ($engine) {
            'mysql' => $this->executeMysql($connConfig, $query),
            'pgsql' => $this->executePgsql($connConfig, $query),
            'sqlite' => $this->executeSqlite($connConfig, $query),
            default => $this->handleUnsupportedEngine($engine),
        };
    }

    private function executeViaAdapter(OutputInterface $output, string $query, bool $clientAvailable): int
    {
        // query() runs a single statement, so trailing statements in a multi-statement string
        // would be silently dropped. Refuse them and point to a path that actually works here:
        // the native client only when its binary exists, otherwise one statement per call.
        if ($this->containsMultipleStatements($query)) {
            $hint = $clientAvailable
                ? 'run them with --driver=client or db:connect.'
                : 'run each statement in a separate db:query call '
                    . '(no native client binary is available on this host).';
            $this->writeError(
                $output,
                'Multiple SQL statements are not supported on the framework connection; ' . $hint,
            );
            return Command::INVALID;
        }

        // Write connection: it can both read and run DML, so a single path serves SELECT and
        // INSERT/UPDATE/DELETE/DDL without choosing a connection per statement type.
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        if (!$connection instanceof \Maho\Db\Adapter\AdapterInterface) {
            $this->writeError($output, 'Unable to resolve the framework database connection.');
            return Command::FAILURE;
        }

        try {
            $statement = $connection->query($query);
            $columnCount = $statement->columnCount();

            // columnCount() is the reliable discriminator: > 0 means the statement produced a
            // result set (SELECT / SHOW / DESCRIBE / EXPLAIN); 0 means a non-result statement
            // (INSERT / UPDATE / DELETE / DDL). It also avoids fetching from a statement that
            // has no result set.
            if ($columnCount === 0) {
                $output->writeln(sprintf('%d row(s) affected.', $statement->rowCount()));
                return Command::SUCCESS;
            }

            $rows = $statement->fetchAll();
        } catch (\Throwable $e) {
            // Surface a one-line error and a clean exit code, mirroring the native-client path,
            // instead of letting the exception become a multi-frame console stack dump.
            $this->writeError($output, $e->getMessage());
            return Command::FAILURE;
        }

        if ($rows === []) {
            $output->writeln('Empty result set.');
            return Command::SUCCESS;
        }

        // Associative fetch collapses duplicate column labels (e.g. SELECT a.id, b.id), which
        // would silently drop a column. Refuse rather than render a misleading result.
        if ($columnCount !== count($rows[0])) {
            $this->writeError(
                $output,
                'The result set has duplicate or ambiguous column names; render it with '
                    . '--driver=client, or alias the columns (e.g. SELECT a.id AS a_id, b.id AS b_id).',
            );
            return Command::FAILURE;
        }

        $table = new Table($output);
        $table->setHeaders(array_keys($rows[0]));
        foreach ($rows as $row) {
            $table->addRow(array_map(
                static fn(mixed $value): string => $value === null ? 'NULL' : (string) $value,
                array_values($row),
            ));
        }
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * Write a diagnostic to STDERR. Uses the console's dedicated error stream when available
     * (so it stays off STDOUT and is assertable in tests) and OUTPUT_RAW so a message that
     * happens to contain "<...>" is not parsed as console markup.
     */
    private function writeError(OutputInterface $output, string $message): void
    {
        $stream = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stream->writeln($message, OutputInterface::OUTPUT_RAW);
    }

    private function executeMysql(mixed $connConfig, string $query): int
    {
        $host = (string) $connConfig->host;
        $dbname = (string) $connConfig->dbname;
        $user = (string) $connConfig->username;
        $password = (string) $connConfig->password;

        $configFile = $this->createTempMySQLConfig($host, $user, $password);

        // Prefer whichever client is installed: "mariadb" on modern MariaDB, "mysql" elsewhere
        // (and as the MariaDB compat symlink). Both accept the same flags.
        $binary = $this->resolveClientBinary('mysql') ?? 'mysql';

        $command = sprintf(
            '%s --defaults-extra-file=%s %s -e %s',
            escapeshellarg($binary),
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
