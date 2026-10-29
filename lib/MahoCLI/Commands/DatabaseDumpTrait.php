<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Connection resolution and native-client pipelines shared by db:export and db:import.
 *
 * The connection is read straight from app/etc/local.xml rather than through Mage,
 * because importing must work while the database is empty, missing, or unreadable,
 * which is exactly when the framework cannot boot.
 *
 * Every pipeline is built as a plain shell string, so the same text is what
 * --dry-run prints and what is executed.
 */
trait DatabaseDumpTrait
{
    use DatabaseCliTrait;

    /** Values accepted by --compression. */
    public const COMPRESSIONS = ['gzip', 'zstd', 'none'];

    /** Engines the dump pipelines can drive. */
    public const DUMP_ENGINES = ['mysql', 'pgsql', 'sqlite'];

    /** First bytes of a gzip and of a zstd stream. */
    private const MAGIC_GZIP = "\x1f\x8b";
    private const MAGIC_ZSTD = "\x28\xb5\x2f\xfd";

    /**
     * Dump tool and default port for each engine.
     *
     * @return array{0: string, 1: string}
     */
    private function engineDumpSettings(string $engine): array
    {
        return match ($engine) {
            'mysql' => ['mysqldump', '3306'],
            'pgsql' => ['pg_dump', '5432'],
            'sqlite' => ['sqlite3', ''],
            default => ['', ''],
        };
    }

    /**
     * Project root: the constant the CLI entry point defines, with the Composer
     * root package path as a fallback for calls made outside the CLI.
     */
    private function projectRoot(): string
    {
        return defined('MAHO_ROOT_DIR') ? MAHO_ROOT_DIR : \Maho::getBasePath();
    }

    /**
     * Connection settings from local.xml, with the engine resolved.
     *
     * @return array{engine: string, host: string, port: string, dbname: string, username: string, password: string}
     * @throws \RuntimeException when local.xml is missing, unparsable, or has no connection node
     */
    protected function readConnectionConfig(): array
    {
        $path = $this->projectRoot() . '/app/etc/local.xml';
        if (!is_file($path)) {
            throw new \RuntimeException("local.xml not found at {$path}");
        }

        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            throw new \RuntimeException("Cannot parse {$path}");
        }

        $node = $xml->global->resources->default_setup->connection ?? null;
        if ($node === null) {
            throw new \RuntimeException("global/resources/default_setup/connection is missing from {$path}");
        }

        return [
            'engine' => $this->getEngine($node),
            'host' => (string) $node->host,
            'port' => (string) $node->port,
            'dbname' => (string) $node->dbname,
            'username' => (string) $node->username,
            'password' => (string) $node->password,
        ];
    }

    /**
     * The native tool that writes a dump for the engine.
     *
     * @param array<string, string> $conn
     */
    protected function dumpBinary(array $conn): string
    {
        return $this->engineDumpSettings($conn['engine'])[0];
    }

    /**
     * The native client that loads a dump, resolved on PATH when available.
     *
     * @param array<string, string> $conn
     */
    protected function loadBinary(array $conn): string
    {
        return $this->resolveClientBinary($conn['engine']) ?? $this->clientBinaryForEngine($conn['engine']);
    }

    /**
     * Binaries a pipeline needs that are not installed on this host.
     *
     * @param array<string, string> $conn
     * @return list<string>
     */
    protected function missingBinaries(array $conn, string $compression, bool $exporting): array
    {
        $required = [$exporting ? $this->dumpBinary($conn) : $this->loadBinary($conn)];

        $compressor = $this->compressionBinary($compression);
        if ($compressor !== null) {
            $required[] = $compressor;
        }

        return array_values(array_filter(
            array_unique($required),
            static fn(string $binary): bool => $binary !== '' && \Mage::findExecutable($binary) === null,
        ));
    }

    /**
     * Compressor binary for a --compression value, or null when the dump stays plain.
     */
    protected function compressionBinary(string $compression): ?string
    {
        return match ($compression) {
            'gzip' => 'gzip',
            'zstd' => 'zstd',
            default => null,
        };
    }

    /**
     * The `pv` progress bar, when installed. It is a soft dependency: the
     * pipeline is valid without it.
     */
    protected function progressViewer(): ?string
    {
        return \Mage::findExecutable('pv') !== null ? 'pv' : null;
    }

    /**
     * Full export pipeline: dump | [sed] | [pv] | [compressor] > file.
     *
     * @param array<string, string> $conn
     */
    protected function buildExportCommand(array $conn, string $file, string $compression, ?string $pv): string
    {
        $steps = [$this->buildDumpCommand($conn)];

        if ($conn['engine'] === 'mysql') {
            // DEFINER clauses bind routines and views to the account that dumped them and
            // break a restore under a different user, so they are stripped on the way out.
            $steps[] = 'LANG=C LC_CTYPE=C LC_ALL=C sed -e ' . escapeshellarg('s/DEFINER[ ]*=[ ]*[^*]*\*/\*/');
        }
        if ($pv !== null) {
            $steps[] = $pv;
        }

        $compressor = $this->compressionFilter($compression);
        if ($compressor !== null) {
            $steps[] = $compressor;
        }

        return implode(' | ', $steps) . ' > ' . escapeshellarg($file);
    }

    /**
     * Full import pipeline: [pv file |] [gunzip file |] client < file.
     *
     * @param array<string, string> $conn
     */
    protected function buildImportCommand(array $conn, string $file, string $compression, ?string $pv): string
    {
        $receiver = $this->buildLoadCommand($conn);
        $decompressor = $this->decompressionFilter($compression);
        $quotedFile = escapeshellarg($file);

        if ($decompressor === null && $pv === null) {
            return "{$receiver} < {$quotedFile}";
        }

        // pv streams the bytes itself, so the file is read once; without it the
        // decompressor still takes the file name as an argument.
        if ($pv !== null) {
            $steps = ["{$pv} {$quotedFile}"];
            if ($decompressor !== null) {
                $steps[] = $decompressor;
            }
        } else {
            $steps = ["{$decompressor} {$quotedFile}"];
        }
        $steps[] = $receiver;

        return implode(' | ', $steps);
    }

    /**
     * Commands that empty the database before the dump is loaded (--drop-tables).
     *
     * @param array<string, string> $conn
     * @return list<string>
     */
    protected function buildResetCommands(array $conn): array
    {
        $dbname = $conn['dbname'];

        return match ($conn['engine']) {
            'mysql' => [
                sprintf(
                    '%s -e %s',
                    $this->buildMysqlClient($conn),
                    escapeshellarg('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($conn, $dbname)),
                ),
                sprintf(
                    '%s -e %s',
                    $this->buildMysqlClient($conn),
                    escapeshellarg(sprintf(
                        'CREATE DATABASE %s DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                        $this->quoteIdentifier($conn, $dbname),
                    )),
                ),
            ],
            // PostgreSQL has no cheap "drop the database" from inside it (and the
            // session owns the current database), so the schema is what gets reset.
            'pgsql' => [sprintf(
                '%s -c %s',
                $this->buildPgsqlClient($conn),
                escapeshellarg('DROP SCHEMA public CASCADE; CREATE SCHEMA public'),
            )],
            'sqlite' => [sprintf(
                'rm -f %s %s %s',
                escapeshellarg($this->sqliteDatabaseFile($dbname)),
                escapeshellarg($this->sqliteDatabaseFile($dbname) . '-wal'),
                escapeshellarg($this->sqliteDatabaseFile($dbname) . '-shm'),
            )],
            default => [],
        };
    }

    /**
     * Whether the dump file is in a format other than the one requested, judged from
     * its first bytes. Compression is not auto-detected, but a mismatch is caught
     * before the client fails with "ASCII '\0' appeared in the statement".
     */
    protected function detectCompression(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }

        $magic = (string) fread($handle, 4);
        fclose($handle);

        return match (true) {
            str_starts_with($magic, self::MAGIC_GZIP) => 'gzip',
            str_starts_with($magic, self::MAGIC_ZSTD) => 'zstd',
            default => null,
        };
    }

    /**
     * Absolute path of a SQLite database: local.xml may carry a bare file name,
     * which lives under var/db, or an absolute one.
     */
    protected function sqliteDatabaseFile(string $dbname): string
    {
        if (str_starts_with($dbname, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $dbname) === 1) {
            return $dbname;
        }

        return $this->projectRoot() . '/var/db/' . $dbname;
    }

    /**
     * Print the resolved connection and every command a run would execute, without
     * running anything. The commands are printed exactly as they would be executed,
     * credentials included, so they can be pasted into a shell to debug a failure.
     *
     * @param array<string, string> $conn
     * @param list<string> $commands
     */
    protected function renderDryRun(OutputInterface $output, array $conn, array $commands): void
    {
        $output->writeln('<info>Dry run: nothing has been executed.</info>');
        $output->writeln('');
        $output->writeln('Connection (app/etc/local.xml):');
        $output->writeln(sprintf('  Engine:   %s', $conn['engine']));
        $output->writeln(sprintf('  Host:     %s', $conn['host'] === '' ? '-' : $conn['host']));
        $output->writeln(sprintf('  Port:     %s', $conn['port'] === '' ? '-' : $conn['port']));
        $output->writeln(sprintf('  Database: %s', $conn['dbname']));
        $output->writeln(sprintf('  User:     %s', $conn['username'] === '' ? '-' : $conn['username']));
        $output->writeln(sprintf('  Password: %s', $conn['password'] === '' ? '-' : $conn['password']));
        $output->writeln('');
        $output->writeln('Command(s):');
        foreach ($commands as $command) {
            $output->writeln($command, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Report a problem on STDERR, so it stays off STDOUT and out of the dry-run output.
     */
    protected function writeError(OutputInterface $output, string $message): void
    {
        $stream = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stream->writeln($message, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Run a shell command, streaming its STDERR (progress bars included) to the
     * terminal. Where bash exists it is used with pipefail, so a failure in the
     * middle of a pipeline is not masked by the compressor succeeding.
     */
    protected function runShellCommand(string $command): int
    {
        $bash = \Mage::findExecutable('bash');
        $wrapped = $bash === null
            ? $command
            : sprintf('%s -o pipefail -c %s', escapeshellarg($bash), escapeshellarg($command));

        passthru($wrapped, $exitCode);

        return $exitCode;
    }

    /**
     * Human-readable byte size. Mage::helper('core') cannot be used here: these
     * commands run without a framework bootstrap.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return $unit === 0 ? $bytes . ' B' : sprintf('%.1f %s', $value, $units[$unit]);
    }

    /**
     * @param array<string, string> $conn
     */
    private function buildDumpCommand(array $conn): string
    {
        $binary = $this->dumpBinary($conn);

        return match ($conn['engine']) {
            'mysql' => implode(' ', [
                $binary,
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                ...$this->mysqlConnectionArguments($conn),
                escapeshellarg($conn['dbname']),
            ]),
            'pgsql' => implode(' ', [
                ...$this->pgsqlEnvironment($conn),
                $binary,
                '--no-owner',
                '--no-privileges',
                ...$this->pgsqlConnectionArguments($conn),
                escapeshellarg($conn['dbname']),
            ]),
            'sqlite' => implode(' ', [
                $binary,
                escapeshellarg($this->sqliteDatabaseFile($conn['dbname'])),
                '.dump',
            ]),
            default => '',
        };
    }

    /**
     * @param array<string, string> $conn
     */
    private function buildLoadCommand(array $conn): string
    {
        return match ($conn['engine']) {
            'mysql' => implode(' ', [
                ...[$this->loadBinary($conn)],
                ...$this->mysqlConnectionArguments($conn),
                escapeshellarg($conn['dbname']),
            ]),
            'pgsql' => implode(' ', [
                ...$this->pgsqlEnvironment($conn),
                $this->loadBinary($conn),
                '-q',
                // pg_dump closes every sequence with a setval() SELECT: keep its
                // result rows off the terminal, errors still reach STDERR.
                '-o',
                '/dev/null',
                '-v',
                'ON_ERROR_STOP=1',
                ...$this->pgsqlConnectionArguments($conn),
                '-d',
                escapeshellarg($conn['dbname']),
            ]),
            'sqlite' => implode(' ', [
                $this->loadBinary($conn),
                escapeshellarg($this->sqliteDatabaseFile($conn['dbname'])),
            ]),
            default => '',
        };
    }

    /**
     * mysql client with its connection arguments, without a database: used to
     * drop and recreate the database itself.
     *
     * @param array<string, string> $conn
     */
    private function buildMysqlClient(array $conn): string
    {
        return implode(' ', [$this->loadBinary($conn), ...$this->mysqlConnectionArguments($conn)]);
    }

    /**
     * @param array<string, string> $conn
     */
    private function buildPgsqlClient(array $conn): string
    {
        return implode(' ', [
            ...$this->pgsqlEnvironment($conn),
            $this->loadBinary($conn),
            '-q',
            ...$this->pgsqlConnectionArguments($conn),
            '-d',
            escapeshellarg($conn['dbname']),
        ]);
    }

    /**
     * @param array<string, string> $conn
     * @return list<string>
     */
    private function mysqlConnectionArguments(array $conn): array
    {
        $arguments = [];
        $defaultPort = $this->engineDumpSettings('mysql')[1];

        if ($conn['host'] !== '') {
            $arguments[] = '-h';
            $arguments[] = escapeshellarg($conn['host']);
        }
        if ($conn['port'] !== '' && $conn['port'] !== $defaultPort) {
            $arguments[] = '-P';
            $arguments[] = escapeshellarg($conn['port']);
        }
        if ($conn['username'] !== '') {
            $arguments[] = '-u' . escapeshellarg($conn['username']);
        }
        // Without a password -p alone would turn into an interactive prompt.
        if ($conn['password'] !== '') {
            $arguments[] = '-p' . escapeshellarg($conn['password']);
        }

        return $arguments;
    }

    /**
     * @param array<string, string> $conn
     * @return list<string>
     */
    private function pgsqlConnectionArguments(array $conn): array
    {
        $arguments = [];
        $defaultPort = $this->engineDumpSettings('pgsql')[1];

        if ($conn['host'] !== '') {
            $arguments[] = '-h';
            $arguments[] = escapeshellarg($conn['host']);
        }
        if ($conn['port'] !== '' && $conn['port'] !== $defaultPort) {
            $arguments[] = '-p';
            $arguments[] = escapeshellarg($conn['port']);
        }
        if ($conn['username'] !== '') {
            $arguments[] = '-U';
            $arguments[] = escapeshellarg($conn['username']);
        }

        return $arguments;
    }

    /**
     * PGPASSWORD as a command prefix, kept out of the argument list where it
     * would show up in `ps` for every process in the pipeline.
     *
     * @param array<string, string> $conn
     * @return list<string>
     */
    private function pgsqlEnvironment(array $conn): array
    {
        return $conn['password'] === '' ? [] : ['PGPASSWORD=' . escapeshellarg($conn['password'])];
    }

    /**
     * @param array<string, string> $conn
     */
    private function quoteIdentifier(array $conn, string $identifier): string
    {
        return $conn['engine'] === 'mysql'
            ? '`' . str_replace('`', '``', $identifier) . '`'
            : '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function compressionFilter(string $compression): ?string
    {
        return match ($compression) {
            'gzip' => 'gzip -c',
            'zstd' => 'zstd -q -c',
            default => null,
        };
    }

    private function decompressionFilter(string $compression): ?string
    {
        return match ($compression) {
            'gzip' => 'gzip -dc',
            'zstd' => 'zstd -dc',
            default => null,
        };
    }
}
