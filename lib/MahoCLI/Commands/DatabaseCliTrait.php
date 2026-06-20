<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Process\ExecutableFinder;

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
     * Map a database engine to the name of its interactive CLI client binary.
     * Returns an empty string for engines that have no known client.
     */
    private function clientBinaryForEngine(string $engine): string
    {
        return match ($engine) {
            'mysql' => 'mysql',
            'pgsql' => 'psql',
            'sqlite' => 'sqlite3',
            default => '',
        };
    }

    /**
     * Whether the native CLI client for the given engine is available on PATH.
     */
    private function isClientBinaryAvailable(string $engine): bool
    {
        $binary = $this->clientBinaryForEngine($engine);
        if ($binary === '') {
            return false;
        }

        // ExecutableFinder is cross-platform (resolves "mysql.exe" on Windows), spawns no shell,
        // and is not defeated by disable_functions — unlike a shell_exec('command -v ...') probe.
        return (new ExecutableFinder())->find($binary) !== null;
    }

    /**
     * Whether the SQL string contains more than one statement.
     */
    private function containsMultipleStatements(string $sql): bool
    {
        return count($this->splitSqlStatements($sql)) > 1;
    }

    /**
     * Split a SQL string into individual statements, ignoring semicolons that appear inside
     * string literals, quoted identifiers, or comments. Empty fragments are dropped.
     *
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            // Line comment: "# ..." or "-- ..." (the latter requires whitespace/EOL after --).
            $dashComment = $char === '-' && $next === '-' && (($i + 2 >= $length) || ctype_space($sql[$i + 2]));
            if ($char === '#' || $dashComment) {
                $newline = strpos($sql, "\n", $i);
                $i = $newline === false ? $length : $newline + 1;
                continue;
            }

            // Block comment: /* ... */
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 2;
                continue;
            }

            // Quoted string ('...', "...") or quoted identifier (`...`).
            if ($char === "'" || $char === '"' || $char === '`') {
                $current .= $char;
                $i++;
                while ($i < $length) {
                    $c = $sql[$i];
                    $current .= $c;
                    // Backslash escape (MySQL string literals; not for backtick identifiers).
                    if ($c === '\\' && $char !== '`' && $i + 1 < $length) {
                        $current .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($c === $char) {
                        // A doubled quote is an escaped quote, not a terminator.
                        if (($sql[$i + 1] ?? '') === $char) {
                            $current .= $char;
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $statements[] = $current;

        return array_values(array_filter(
            array_map('trim', $statements),
            static fn(string $statement): bool => $statement !== '',
        ));
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

    private function scheduleFileDeletion(string $filename): void
    {
        exec(sprintf(
            '(sleep 1 && rm -f %s) > /dev/null 2>&1 &',
            escapeshellarg($filename),
        ));
    }
}
