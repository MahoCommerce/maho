<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\DBExport;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

if (!defined('MAHO_ROOT_DIR')) {
    define('MAHO_ROOT_DIR', Maho::getBasePath());
}

/**
 * Coverage for db:export: option validation and the per-engine pipelines.
 * Pipelines are built as plain shell strings, so they are asserted directly
 * without running any client binary.
 */

function dbExportTester(): CommandTester
{
    $command = new DBExport('db:export');
    (new Application())->addCommand($command);

    return new CommandTester($command);
}

/**
 * @param array<string, string> $overrides
 * @return array<string, string>
 */
function dbExportConnection(array $overrides = []): array
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

function dbExportReflect(string $method, mixed ...$arguments): mixed
{
    static $command = null;
    $command ??= new DBExport('db:export');

    return (new ReflectionMethod(DBExport::class, $method))->invoke($command, ...$arguments);
}

it('rejects an unknown --compression value', function () {
    $result = dbExportTester()->run(['file' => 'dump.sql', '--compression' => 'bzip2']);

    $this->assertCommandIsInvalid($result);
    expect($result->getErrorOutput())->toContain('Invalid --compression value: bzip2');
});

it('refuses to overwrite an existing dump without --force', function () {
    $file = sys_get_temp_dir() . '/maho_dbexport_exists.sql';
    file_put_contents($file, 'x');

    try {
        $result = dbExportTester()->run(['file' => $file]);
        $this->assertCommandFailed($result);
        expect($result->getErrorOutput())->toContain('already exists');
    } finally {
        unlink($file);
    }
});

it('builds a MySQL pipeline with inline credentials, definer stripping and compression', function () {
    $command = dbExportReflect('buildExportCommand', dbExportConnection(), '/tmp/dump.sql.gz', 'gzip', 'pv');

    expect($command)->toStartWith('mysqldump --single-transaction --quick --no-tablespaces')
        ->toContain("-h 'db'")
        ->toContain("-u'maho'")
        ->toContain("-p'maho'")
        ->toContain("sed -e 's/DEFINER[ ]*=[ ]*[^*]*\\*/\\*/'")
        ->toContain('| pv | gzip -c')
        ->toEndWith("> '/tmp/dump.sql.gz'");
});

it('passes a non-default MySQL port to the client', function () {
    $command = dbExportReflect('buildExportCommand', dbExportConnection(['port' => '3307']), '/tmp/dump.sql', 'none', null);

    expect($command)->toContain("-P '3307'");
});

it('leaves the dump plain with --compression=none', function () {
    $command = dbExportReflect('buildExportCommand', dbExportConnection(), '/tmp/dump.sql', 'none', null);

    expect($command)->not->toContain('gzip')
        ->not->toContain('zstd')
        ->toEndWith("> '/tmp/dump.sql'");
});

it('builds a PostgreSQL pipeline that keeps the password out of the argument list', function () {
    $command = dbExportReflect('buildExportCommand', dbExportConnection([
        'engine' => 'pgsql',
        'port' => '5433',
    ]), '/tmp/dump.sql.zst', 'zstd', null);

    expect($command)->toStartWith("PGPASSWORD='maho' pg_dump --no-owner --no-privileges")
        ->toContain("-h 'db'")
        ->toContain("-p '5433'")
        ->toContain("-U 'maho'")
        ->toContain('| zstd -q -c')
        ->not->toContain('-p\'maho\'')
        ->not->toContain('sed');
});

it('builds a SQLite pipeline against the database file under var/db', function () {
    $command = dbExportReflect('buildExportCommand', dbExportConnection([
        'engine' => 'sqlite',
        'dbname' => 'maho.sqlite',
    ]), '/tmp/dump.sql', 'none', null);

    expect($command)->toBe(
        "sqlite3 '" . MAHO_ROOT_DIR . "/var/db/maho.sqlite' .dump > '/tmp/dump.sql'",
    );
});

it('keeps an absolute SQLite database path untouched', function () {
    expect(dbExportReflect('sqliteDatabaseFile', '/var/data/shop.sqlite'))->toBe('/var/data/shop.sqlite')
        ->and(dbExportReflect('sqliteDatabaseFile', 'shop.sqlite'))->toBe(MAHO_ROOT_DIR . '/var/db/shop.sqlite');
});

it('prints the resolved connection and the command in --dry-run mode', function () {
    $conn = dbExportConnection(['host' => 'db.internal', 'password' => 'secret']);
    $command = "mysqldump -p'secret' maho > '/tmp/dump.sql'";

    $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
    dbExportReflect('renderDryRun', $buffer, $conn, [$command]);
    $display = $buffer->fetch();

    expect($display)->toContain('Dry run')
        ->toContain('db.internal')
        ->toContain('secret')
        ->toContain($command);
});

it('formats dump sizes in human readable units', function () {
    expect(dbExportReflect('formatBytes', 512))->toBe('512 B')
        ->and(dbExportReflect('formatBytes', 2048))->toBe('2.0 KB')
        ->and(dbExportReflect('formatBytes', 13702261))->toBe('13.1 MB');
});
