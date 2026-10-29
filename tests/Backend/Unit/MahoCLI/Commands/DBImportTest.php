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
    (new Application())->addCommand($command);

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

    return (new ReflectionMethod(DBImport::class, $method))->invoke($command, ...$arguments);
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

    expect($command)->toBe("mysql -h 'db' -u'maho' -p'maho' 'maho' < '/tmp/dump.sql'");
});

it('streams a compressed dump through pv exactly once', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection(), '/tmp/dump.sql.gz', 'gzip', 'pv');

    expect($command)->toStartWith("pv '/tmp/dump.sql.gz' | gzip -dc | mysql")
        ->not->toContain('cat ');
});

it('streams a plain dump through pv without reading the file twice', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection(), '/tmp/dump.sql', 'none', 'pv');

    expect($command)->toBe("pv '/tmp/dump.sql' | mysql -h 'db' -u'maho' -p'maho' 'maho'");
});

it('builds a PostgreSQL load command that stops on the first error', function () {
    $command = dbImportReflect('buildImportCommand', dbImportConnection([
        'engine' => 'pgsql',
    ]), '/tmp/dump.sql.zst', 'zstd', null);

    expect($command)->toBe(
        "zstd -dc '/tmp/dump.sql.zst' | PGPASSWORD='maho' psql -q -o /dev/null -v ON_ERROR_STOP=1"
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
