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
use Symfony\Component\Console\Tester\ExecutionResult;
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
    new Application()->addCommand($command);

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

    return new ReflectionMethod(DBExport::class, $method)->invoke($command, ...$arguments);
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

it('builds a MySQL pipeline without the password, with definer stripping and compression', function () {
    $command = dbExportReflect('buildExportCommand', dbExportConnection(), '/tmp/dump.sql.gz', 'gzip', 'pv');

    expect($command)->toMatch('/^(mysqldump|mariadb-dump) --single-transaction --quick --no-tablespaces/')
        ->toContain("-h 'db'")
        ->toContain("-u'maho'")
        ->not->toContain('-p')
        ->toContain('LANG=C LC_CTYPE=C LC_ALL=C sed -e')
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

    expect($command)->toStartWith('pg_dump --no-owner --no-privileges')
        ->not->toContain('PGPASSWORD')
        ->toContain("-h 'db'")
        ->toContain("-p '5433'")
        ->toContain("-U 'maho'")
        ->toContain('| zstd -q -c')
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
    $command = "mysqldump maho > '/tmp/dump.sql'";

    $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
    dbExportReflect('renderDryRun', $buffer, $conn, [$command]);
    $display = $buffer->fetch();

    expect($display)->toContain('Dry run')
        ->toContain('db.internal')
        ->toContain('********')
        ->not->toContain('secret')
        ->toContain($command);
});

it('removes a DEFINER clause but never changes a data row', function () {
    $pipeline = dbExportReflect('buildExportCommand', dbExportConnection(), '/tmp/dump.sql', 'none', null);
    $sed = current(array_filter(explode(' | ', $pipeline), static fn(string $step): bool => str_starts_with($step, 'LANG=C')));
    $sed = preg_replace('/ > .*$/', '', $sed);
    $input = "/*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER t\nINSERT INTO x VALUES ('DEFINER = x */ y');\n";

    expect(shell_exec('printf %s ' . escapeshellarg($input) . ' | ' . $sed))
        ->toBe("/*!50017 */ /*!50003 TRIGGER t\nINSERT INTO x VALUES ('DEFINER = x */ y');\n");
});

it('passes the password in the environment and removes it afterwards', function (string $engine, string $variable) {
    ob_start();
    $code = dbExportReflect('runShellCommand', 'printf %s "$' . $variable . '"', dbExportConnection(['engine' => $engine, 'password' => 'se cret']));
    $output = ob_get_clean();

    expect($output)->toBe('se cret')
        ->and($code)->toBe(0)
        ->and(getenv($variable))->toBeFalse();
})->with([['mysql', 'MYSQL_PWD'], ['pgsql', 'PGPASSWORD']]);

it('keeps the password of the shell when local.xml has none', function () {
    putenv('MYSQL_PWD=from-shell');

    try {
        ob_start();
        dbExportReflect('runShellCommand', 'printf %s "$MYSQL_PWD"', dbExportConnection(['password' => '']));
        $output = ob_get_clean();
        $after = getenv('MYSQL_PWD');
    } finally {
        putenv('MYSQL_PWD');
    }

    expect($output)->toBe('from-shell')
        ->and($after)->toBe('from-shell');
});

it('uses mariadb-dump when mysqldump is not installed', function () {
    $directory = sys_get_temp_dir() . '/maho_dbexport_bin_' . uniqid();
    mkdir($directory);
    file_put_contents($directory . '/mariadb-dump', "#!/bin/sh\n");
    chmod($directory . '/mariadb-dump', 0755);
    $path = (string) getenv('PATH');
    putenv('PATH=' . $directory);

    try {
        if (Mage::findExecutable('mysqldump') !== null) {
            $this->markTestSkipped('mysqldump is installed in a fixed directory');
        }
        expect(dbExportReflect('dumpBinary', dbExportConnection()))->toBe('mariadb-dump');
    } finally {
        putenv('PATH=' . $path);
        unlink($directory . '/mariadb-dump');
        rmdir($directory);
    }
});

it('splits a host:port value and sends a socket path to --socket', function () {
    $split = dbExportReflect('buildExportCommand', dbExportConnection(['host' => 'db:3307']), '/tmp/dump.sql', 'none', null);
    $socket = dbExportReflect('buildExportCommand', dbExportConnection(['host' => '/run/mysqld/mysqld.sock']), '/tmp/dump.sql', 'none', null);

    expect($split)->toContain("-h 'db' -P '3307'")
        ->and($socket)->toContain("--socket='/run/mysqld/mysqld.sock'")
        ->not->toContain('-h ');
});

it('fails a pipeline when a step before the last one fails', function () {
    expect(dbExportReflect('runShellCommand', 'false | true', dbExportConnection(['engine' => 'sqlite'])))->not->toBe(0);
});

/**
 * Command whose shell calls are recorded instead of run.
 */
function dbExportRecordingCommand(int $exitCode, ?string $writeFile = null): DBExport
{
    return new class ('db:export', $exitCode, $writeFile) extends DBExport {
        /** @var list<string> */
        public array $ran = [];

        public function __construct(string $name, private int $exitCode, private ?string $writeFile)
        {
            parent::__construct($name);
        }

        #[\Override]
        protected function readConnectionConfig(): array
        {
            return dbExportConnection();
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
            if ($this->writeFile !== null) {
                file_put_contents($this->writeFile, 'partial');
            }

            return $this->exitCode;
        }
    };
}

function dbExportRunRecording(DBExport $command, array $input): ExecutionResult
{
    new Application()->addCommand($command);

    return new CommandTester($command)->run($input);
}

it('keeps the existing dump and removes the partial file when a forced export fails', function () {
    $file = sys_get_temp_dir() . '/maho_dbexport_fail.sql';
    file_put_contents($file, 'old');
    $command = dbExportRecordingCommand(1, $file . '.part');

    try {
        $result = dbExportRunRecording($command, ['file' => $file, '--compression' => 'none', '--force' => true]);
        $this->assertCommandFailed($result);
        expect(is_file($file . '.part'))->toBeFalse()
            ->and(file_get_contents($file))->toBe('old');
    } finally {
        @unlink($file);
        @unlink($file . '.part');
    }
});

it('replaces the dump with a file that only the owner can read', function () {
    $file = sys_get_temp_dir() . '/maho_dbexport_mode.sql';
    file_put_contents($file, 'old');
    chmod($file, 0644);
    $command = dbExportRecordingCommand(0, $file . '.part');
    $umask = umask();

    try {
        $result = dbExportRunRecording($command, ['file' => $file, '--compression' => 'none', '--force' => true]);
        $this->assertCommandIsSuccessful($result);
        expect(file_get_contents($file))->toBe('partial')
            ->and(fileperms($file) & 0777)->toBe(0600)
            ->and(umask())->toBe($umask);
    } finally {
        @unlink($file);
        @unlink($file . '.part');
    }
});

it('runs no command and ignores an existing file with --dry-run', function () {
    $file = sys_get_temp_dir() . '/maho_dbexport_dry.sql';
    file_put_contents($file, 'x');
    $command = dbExportRecordingCommand(0);

    try {
        $result = dbExportRunRecording($command, ['file' => $file, '--dry-run' => true]);
        $this->assertCommandIsSuccessful($result);
        expect($command->ran)->toBe([])
            ->and($result->getDisplay())->toContain('Dry run');
    } finally {
        unlink($file);
    }
});
