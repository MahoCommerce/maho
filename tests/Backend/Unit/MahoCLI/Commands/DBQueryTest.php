<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\DBQuery;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the db:query framework-adapter execution path (--driver=adapter).
 * The adapter path runs through the application's own database connection, so it
 * works on hosts that ship no native client binary. SQL is kept engine-portable
 * so the suite passes under the mysql, pgsql, and sqlite test runs.
 */

function dbQueryTester(): CommandTester
{
    $command = new DBQuery('db:query');
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command);
}

function dbQueryReflect(string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod(DBQuery::class, $method);
    return $ref->invoke(new DBQuery('db:query'), ...$args);
}

it('renders a SELECT result set as a table via the framework adapter', function () {
    $tester = dbQueryTester();
    $tester->execute(['query' => "SELECT 1 AS num, 'hello' AS word", '--driver' => 'adapter']);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $display = $tester->getDisplay();
    expect($display)->toContain('num')
        ->and($display)->toContain('word')
        ->and($display)->toContain('hello');
});

it('reports affected rows for a non-result statement', function () {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->query('CREATE TEMPORARY TABLE maho_dbquery_probe (id INT)');

    $tester = dbQueryTester();
    $tester->execute(['query' => 'INSERT INTO maho_dbquery_probe (id) VALUES (1), (2), (3)', '--driver' => 'adapter']);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('3 row(s) affected');
});

it('prints an empty-result-set notice when a query returns no rows', function () {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->query('CREATE TEMPORARY TABLE maho_dbquery_empty (id INT)');

    $tester = dbQueryTester();
    $tester->execute(['query' => 'SELECT id FROM maho_dbquery_empty', '--driver' => 'adapter']);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('Empty result set');
});

it('fails cleanly on an invalid query, reporting the error on STDERR', function () {
    $tester = dbQueryTester();
    $tester->execute(
        ['query' => 'SELECT * FROM maho_dbquery_no_such_table', '--driver' => 'adapter'],
        ['capture_stderr_separately' => true],
    );

    expect($tester->getStatusCode())->toBe(Command::FAILURE);
    expect($tester->getErrorOutput())->toContain('maho_dbquery_no_such_table');
});

it('rejects an unknown --driver value on STDERR', function () {
    $tester = dbQueryTester();
    $tester->execute(
        ['query' => 'SELECT 1', '--driver' => 'bogus'],
        ['capture_stderr_separately' => true],
    );

    expect($tester->getStatusCode())->toBe(Command::INVALID);
    expect($tester->getErrorOutput())->toContain('Invalid --driver value');
});

it('refuses multiple statements on the adapter path instead of running only the first', function () {
    $tester = dbQueryTester();
    $tester->execute(
        ['query' => 'SELECT 1 AS a; SELECT 2 AS b', '--driver' => 'adapter'],
        ['capture_stderr_separately' => true],
    );

    expect($tester->getStatusCode())->toBe(Command::INVALID);
    expect($tester->getErrorOutput())->toContain('Multiple SQL statements');
});

it('does not treat a semicolon inside a string literal as multiple statements', function () {
    $tester = dbQueryTester();
    $tester->execute(['query' => "SELECT 'a;b' AS semi, 1 AS n", '--driver' => 'adapter']);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('a;b');
});

it('refuses a result set with duplicate column names instead of dropping a column', function () {
    $tester = dbQueryTester();
    $tester->execute(
        ['query' => 'SELECT 1 AS id, 2 AS id', '--driver' => 'adapter'],
        ['capture_stderr_separately' => true],
    );

    expect($tester->getStatusCode())->toBe(Command::FAILURE);
    expect($tester->getErrorOutput())->toContain('duplicate or ambiguous column names');
});

it('detects multiple statements while ignoring literals, identifiers, and comments', function () {
    expect(dbQueryReflect('containsMultipleStatements', 'SELECT 1'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1;'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1; SELECT 2'))->toBeTrue()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 'a;b'"))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT "x;y"'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT `a;b`'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1 /* ; */ , 2'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'UPDATE a SET x=1; UPDATE b SET y=2'))->toBeTrue();
});

it('maps each engine to its client binary name', function () {
    expect(dbQueryReflect('clientBinaryForEngine', 'mysql'))->toBe('mysql')
        ->and(dbQueryReflect('clientBinaryForEngine', 'pgsql'))->toBe('psql')
        ->and(dbQueryReflect('clientBinaryForEngine', 'sqlite'))->toBe('sqlite3')
        ->and(dbQueryReflect('clientBinaryForEngine', 'unknown'))->toBe('');
});

it('reports no client binary for an unknown engine', function () {
    expect(dbQueryReflect('isClientBinaryAvailable', 'unknown-engine'))->toBeFalse();
});
