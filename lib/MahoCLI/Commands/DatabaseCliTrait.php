<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait DatabaseCliTrait
{
    private function getEngine(mixed $connConfig): string
    {
        if (!empty($connConfig->engine)) {
            return (string) $connConfig->engine;
        }

        if (!empty($connConfig->type)) {
            $type = (string) $connConfig->type;
            if (str_starts_with($type, 'pdo_')) {
                return substr($type, 4);
            }
            return $type;
        }

        if (!empty($connConfig->model)) {
            return (string) $connConfig->model;
        }

        return 'mysql';
    }

    /**
     * Candidate CLI client binary names for a database engine, in preference order.
     * The 'mysql' engine covers MariaDB too: MariaDB 10.5+ ships the client as "mariadb"
     * and only keeps "mysql" as a back-compat symlink that newer versions deprecate and
     * some slim images omit, so both names are accepted.
     *
     * @return list<string>
     */
    private function clientBinaryCandidatesForEngine(string $engine): array
    {
        return match ($engine) {
            'mysql' => ['mysql', 'mariadb'],
            'pgsql' => ['psql'],
            'sqlite' => ['sqlite3'],
            default => [],
        };
    }

    /**
     * Canonical client binary name for an engine, for diagnostics. Returns an empty string
     * for engines that have no known client.
     */
    private function clientBinaryForEngine(string $engine): string
    {
        return $this->clientBinaryCandidatesForEngine($engine)[0] ?? '';
    }

    /**
     * Resolve the first client binary that is actually available on PATH, or null if none is.
     */
    private function resolveClientBinary(string $engine): ?string
    {
        foreach ($this->clientBinaryCandidatesForEngine($engine) as $candidate) {
            if (\Mage::findExecutable($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether a native CLI client for the given engine is available on PATH.
     */
    private function isClientBinaryAvailable(string $engine): bool
    {
        return $this->resolveClientBinary($engine) !== null;
    }

    /**
     * Whether the SQL string contains more than one statement.
     */
    private function containsMultipleStatements(string $sql, string $engine = 'mysql'): bool
    {
        return count($this->splitSqlStatements($sql, $engine)) > 1;
    }

    /**
     * Split a SQL string into individual statements, ignoring semicolons that appear inside
     * string literals, quoted identifiers, or comments. Empty fragments are dropped.
     *
     * @return list<string>
     */
    private function splitSqlStatements(string $sql, string $engine): array
    {
        return \Maho\Db\Sql\StatementSplitter::split($sql, $engine);
    }

    private function createTempMySQLConfig(
        #[\SensitiveParameter]
        string $host,
        #[\SensitiveParameter]
        string $user,
        #[\SensitiveParameter]
        string $password,
    ): string {
        $configContent = "[client]\nhost=\"$host\"\nuser=\"$user\"\npassword=\"$password\"\n";
        $configFile = tempnam(sys_get_temp_dir(), '.maho_temp_config_');
        chmod($configFile, 0600);
        file_put_contents($configFile, $configContent);

        $this->scheduleFileDeletion($configFile);

        return $configFile;
    }

    private function createTempPgpassFile(
        #[\SensitiveParameter]
        string $host,
        #[\SensitiveParameter]
        string $port,
        #[\SensitiveParameter]
        string $dbname,
        #[\SensitiveParameter]
        string $user,
        #[\SensitiveParameter]
        string $password,
    ): string {
        // pgpass format: hostname:port:database:username:password
        $configContent = sprintf("%s:%s:%s:%s:%s\n", $host, $port, $dbname, $user, $password);
        $configFile = tempnam(sys_get_temp_dir(), '.maho_pgpass_');
        chmod($configFile, 0600);
        file_put_contents($configFile, $configContent);

        $this->scheduleFileDeletion($configFile);

        return $configFile;
    }

    /**
     * Write $message to STDERR. Use OUTPUT_RAW, so that the console does not read "<...>" in the message as a tag.
     */
    private function writeError(OutputInterface $output, string $message): void
    {
        $stream = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stream->writeln($message, OutputInterface::OUTPUT_RAW);
    }

    private function scheduleFileDeletion(string $filename): void
    {
        exec(sprintf(
            '(sleep 1 && rm -f %s) > /dev/null 2>&1 &',
            escapeshellarg($filename),
        ));
    }
}
