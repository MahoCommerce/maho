<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\DBImport;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

if (!defined('MAHO_ROOT_DIR')) {
    define('MAHO_ROOT_DIR', Maho::getBasePath());
}

/**
 * Coverage for db:import: option validation, the compression sniffing that
 * guards against loading a compressed dump as plain SQL, the per-engine
 * pipelines and the --drop-tables statements. No client binary is executed.
 */

function dbImportTester(): CommandTester
{
    $command = new DBImport('db:import');
    new Application()->addCommand($command);

    return new CommandTester($command);
}

/**
 * @param array<string, string> $overrides
 * @return array<string, string>
 */
function dbImportConnection(array $overrides = []): array
{
    return array_merge([
        'engine' => 'mysql',
        'host' => 'db',
        'port' => '',
        'dbname' => 'maho',
        'username' => 'maho',
        'password' => 'maho',
    ], $overrides);
}

function dbImportReflect(string $method, mixed ...$arguments): mixed
{
    static $command = null;
    $command ??= new DBImport('db:import');

    return new ReflectionMethod(DBImport::class, $method)->invoke($command, ...$arguments);
}

/**
 * Write a temporary file with the given leading bytes and hand its path to the callback.
 */
function dbImportWithTempFile(string $magic, Closure $callback): void
{
    $file = (string) tempnam(sys_get_temp_dir(), 'maho_dbimport_');
    file_put_contents($file, $magic . "COALESCE\n");

    try {
        $callback($file);
    } finally {
        unlink($file);
    }
}

it('rejects an unknown --compression value', function () {
    $result = dbImportTester()->run(['file' => 'dump.sql', '--compression' => 'bzip2']);

    $this->assertCommandIsInvalid($result);
    expect($result->getErrorOutput())->toContain('Invalid --compression value: bzip2');
});

it('reports a missing dump file', function () {
    $result = dbImportTester()->run(['file' => sys_get_temp_dir() . '/maho_dbimport_missing.sql']);

    $this->assertCommandFailed($result);
    expect($result->getErrorOutput())->toContain('File not found');
});

it('asks for the dump file when the argument is missing', function () {
    $path = sys_get_temp_dir() . '/maho_dbimport_asked.sql';
    $result = dbImportTester()->run([], [$path]);

    $this->assertCommandFailed($result);
    expect($result->getErrorOutput())->toContain("File not found: {$path}");
});

it('refuses a dump that the user cannot read', function () {
    if (posix_geteuid() === 0) {
        $this->markTestSkipped('root reads every file');
    }

    dbImportWithTempFile('-- Maho', function (string $file): void {
        chmod($file, 0000);
        $result = dbImportTester()->run(['file' => $file, '--drop-tables' => true]);

        $this->assertCommandFailed($result);
        expect($result->getErrorOutput())->toContain('Cannot read file');
    });
});

it('refuses a gzip dump loaded as plain SQL', function () {
    dbImportWithTempFile("\x1f\x8b\x08\x00", function (string $file): void {
        $result = dbImportTester()->run(['file' => $file]);

        $this->assertCommandIsInvalid($result);
        expect($result->getErrorOutput())->toContain('pass --compression=gzip');
    });
});

it('refuses a zstd dump loaded as plain SQL', function () {
    dbImportWithTempFile("\x28\xb5\x2f\xfd", function (string $file): void {
        $result = dbImportTester()->run(['file' => $file]);

        $this->assertCommandIsInvalid($result);
        expect($result->getErrorOutput())->toContain('pass --compression=zstd');
    });
});

it('refuses a plain SQL file declared as compressed', function () {
    dbImportWithTempFile('-- Maho', function (string $file): void {
        $result = dbImportTester()->run(['file' => $file, '--compression' => 'gzip']);

        $this->assertCommandIsInvalid($result);
        expect($result->getErrorOutput())->toContain('does not look gzip-compressed');
    });
});

it('detects the compression of a dump from its magic bytes', function () {
    dbImportWithTempFile("\x1f\x8b\x08\x00", function (string $file): void {
        expect(dbImportReflect('detectCompression', $file))->toBe('gzip');
    });
    dbImportWithTempFile("\x28\xb5\x2f\xfd", function (string $file): void {
        expect(dbImportReflect('detectCompression', $file))->toBe('zstd');
    });
    dbImportWithTempFile('CREATE TABLE', function (string $file): void {
        expect(dbImportReflect('detectCompression', $file))->toBeNull();
    });
});

it('loads a plain dump through a redirect when no progress viewer is available', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection(), '/tmp/dump.sql', 'none', null);

    $client = dbImportReflect('loadBinary', dbImportConnection());

    expect($command)->toBe("{$client} -h 'db' -u'maho' 'maho' < '/tmp/dump.sql'");
});

it('streams a compressed dump through pv exactly once', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection(), '/tmp/dump.sql.gz', 'gzip', 'pv');

    $client = dbImportReflect('loadBinary', dbImportConnection());

    expect($command)->toStartWith("pv '/tmp/dump.sql.gz' | gzip -dc | {$client}")
        ->not->toContain('cat ');
});

it('streams a plain dump through pv without reading the file twice', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection(), '/tmp/dump.sql', 'none', 'pv');

    $client = dbImportReflect('loadBinary', dbImportConnection());

    expect($command)->toBe("pv '/tmp/dump.sql' | {$client} -h 'db' -u'maho' 'maho'");
});

it('builds a PostgreSQL load command that stops on the first error', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection([
        'engine' => 'pgsql',
    ]), '/tmp/dump.sql.zst', 'zstd', null);

    expect($command)->toBe(
        "zstd -dc '/tmp/dump.sql.zst' | psql -q -o /dev/null -v ON_ERROR_STOP=1"
            . " -h 'db' -U 'maho' -d 'maho'",
    );
});

it('recreates the MySQL database with --drop-tables', function () {
    $commands = dbImportReflect('buildResetCommands', dbImportConnection());

    expect($commands)->toHaveCount(2)
        ->and($commands[0])->toContain('DROP DATABASE IF EXISTS `maho`')
        ->and($commands[1])->toContain('CREATE DATABASE `maho` DEFAULT CHARACTER SET utf8mb4');
});

it('resets the PostgreSQL public schema with --drop-tables', function () {
    $commands = dbImportReflect('buildResetCommands', dbImportConnection(['engine' => 'pgsql']));

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toContain('DROP SCHEMA public CASCADE; CREATE SCHEMA public');
});

it('removes the SQLite database file with --drop-tables', function () {
    $commands = dbImportReflect('buildResetCommands', dbImportConnection([
        'engine' => 'sqlite',
        'dbname' => 'maho.sqlite',
    ]));

    $file = MAHO_ROOT_DIR . '/var/db/maho.sqlite';
    expect($commands)->toBe(["rm -f '{$file}' '{$file}-wal' '{$file}-shm'"]);
});

it('stops the SQLite load at the first error', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection([
        'engine' => 'sqlite',
        'dbname' => 'maho.sqlite',
    ]), '/tmp/dump.sql', 'none', null);

    expect($command)->toContain('sqlite3 -bail ');
});

/**
 * Command whose shell calls are recorded instead of run.
 *
 * @param list<int> $exitCodes One exit code for each call, in order.
 * @param array<string, string> $connection
 */
function dbImportRecordingCommand(array $exitCodes, array $connection = []): DBImport
{
    return new class ('db:import', $exitCodes, $connection) extends DBImport {
        /** @var list<string> */
        public array $ran = [];

        /**
         * @param list<int> $exitCodes
         * @param array<string, string> $connection
         */
        public function __construct(string $name, private array $exitCodes, private array $connection)
        {
            parent::__construct($name);
        }

        #[\Override]
        protected function readConnectionConfig(): array
        {
            return dbImportConnection($this->connection);
        }

        #[\Override]
        protected function missingBinaries(array $conn, string $compression, bool $exporting): array
        {
            return [];
        }

        #[\Override]
        protected function runShellCommand(string $command, array $conn): int
        {
            $this->ran[] = $command;

            return $this->exitCodes[count($this->ran) - 1] ?? 0;
        }
    };
}

it('empties the database before the load with --drop-tables', function () {
    $command = dbImportRecordingCommand([]);
    new Application()->addCommand($command);

    dbImportWithTempFile('-- Maho', function (string $file) use ($command): void {
        $result = new CommandTester($command)->run(['file' => $file, '--drop-tables' => true, '--force' => true]);

        $this->assertCommandIsSuccessful($result);
        expect($command->ran)->toHaveCount(3)
            ->and($command->ran[0])->toContain('DROP DATABASE')
            ->and($command->ran[1])->toContain('CREATE DATABASE')
            ->and($command->ran[1])->toContain('COLLATE ' . \Maho\Db\Adapter\Pdo\Mysql::DEFAULT_COLLATION)
            ->and($command->ran[2])->toContain($file);
    });
});

it('runs one reset command before the load for PostgreSQL and SQLite', function (string $engine, string $reset) {
    $command = dbImportRecordingCommand([], ['engine' => $engine, 'dbname' => sys_get_temp_dir() . '/maho_dbimport_reset.sqlite']);
    new Application()->addCommand($command);

    dbImportWithTempFile('-- Maho', function (string $file) use ($command, $reset): void {
        $result = new CommandTester($command)->run(['file' => $file, '--drop-tables' => true, '--force' => true]);

        $this->assertCommandIsSuccessful($result);
        expect($command->ran)->toHaveCount(2)
            ->and($command->ran[0])->toContain($reset)
            ->and($command->ran[1])->toContain($file);
    });
})->with([['pgsql', 'DROP SCHEMA public CASCADE'], ['sqlite', 'rm -f']]);

it('creates the directory of a SQLite database file before the load', function () {
    $directory = sys_get_temp_dir() . '/maho_dbimport_dir_' . uniqid();
    $command = dbImportRecordingCommand([], ['engine' => 'sqlite', 'dbname' => $directory . '/db/shop.sqlite']);
    new Application()->addCommand($command);

    try {
        dbImportWithTempFile('-- Maho', function (string $file) use ($command, $directory): void {
            $result = new CommandTester($command)->run(['file' => $file]);

            $this->assertCommandIsSuccessful($result);
            expect(is_dir($directory . '/db'))->toBeTrue();
        });
    } finally {
        @rmdir($directory . '/db');
        @rmdir($directory);
    }
});

it('stops before the load when the directory of a SQLite database file cannot be created', function () {
    $parent = (string) tempnam(sys_get_temp_dir(), 'maho_dbimport_file_');
    $command = dbImportRecordingCommand([], ['engine' => 'sqlite', 'dbname' => $parent . '/db/shop.sqlite']);
    new Application()->addCommand($command);

    try {
        dbImportWithTempFile('-- Maho', function (string $file) use ($command): void {
            $result = new CommandTester($command)->run(['file' => $file]);

            $this->assertCommandFailed($result);
            expect($command->ran)->toBe([])
                ->and($result->getErrorOutput())->toContain('Cannot create directory');
        });
    } finally {
        unlink($parent);
    }
});

it('refuses an engine that has no client', function () {
    $command = dbImportRecordingCommand([], ['engine' => 'oracle']);
    new Application()->addCommand($command);

    dbImportWithTempFile('-- Maho', function (string $file) use ($command): void {
        $result = new CommandTester($command)->run(['file' => $file]);

        $this->assertCommandFailed($result);
        expect($command->ran)->toBe([])
            ->and($result->getErrorOutput())->toContain('Unsupported database engine: oracle');
    });
});

it('does not load the dump when emptying the database fails', function () {
    $command = dbImportRecordingCommand([1]);
    new Application()->addCommand($command);

    dbImportWithTempFile('-- Maho', function (string $file) use ($command): void {
        $result = new CommandTester($command)->run(['file' => $file, '--drop-tables' => true, '--force' => true]);

        $this->assertCommandFailed($result);
        expect($command->ran)->toHaveCount(1)
            ->and($result->getErrorOutput())->toContain('It imported nothing');
    });
});

it('does not empty the database when the question is not confirmed', function () {
    $command = dbImportRecordingCommand([]);
    new Application()->addCommand($command);

    dbImportWithTempFile('-- Maho', function (string $file) use ($command): void {
        $result = new CommandTester($command)->run(['file' => $file, '--drop-tables' => true], interactive: false);

        $this->assertCommandFailed($result);
        expect($command->ran)->toBe([])
            ->and($result->getErrorOutput())->toContain('--force');
    });
});

it('runs no command with --dry-run', function () {
    $command = dbImportRecordingCommand([]);
    new Application()->addCommand($command);

    dbImportWithTempFile('-- Maho', function (string $file) use ($command): void {
        $result = new CommandTester($command)->run(['file' => $file, '--drop-tables' => true, '--dry-run' => true]);

        $this->assertCommandIsSuccessful($result);
        expect($command->ran)->toBe([]);
    });
});
