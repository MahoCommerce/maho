<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\DBQuery;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

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
    $result = dbQueryTester()->run(['query' => "SELECT 1 AS num, 'hello' AS word", '--driver' => 'adapter']);

    $this->assertCommandIsSuccessful($result);
    $display = $result->getDisplay();
    expect($display)->toContain('num')
        ->and($display)->toContain('word')
        ->and($display)->toContain('hello');
});

it('reports affected rows for a non-result statement', function () {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->query('CREATE TEMPORARY TABLE maho_dbquery_probe (id INT)');

    $result = dbQueryTester()->run(['query' => 'INSERT INTO maho_dbquery_probe (id) VALUES (1), (2), (3)', '--driver' => 'adapter']);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('3 row(s) affected');
});

it('prints an empty-result-set notice when a query returns no rows', function () {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->query('CREATE TEMPORARY TABLE maho_dbquery_empty (id INT)');

    $result = dbQueryTester()->run(['query' => 'SELECT id FROM maho_dbquery_empty', '--driver' => 'adapter']);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('Empty result set');
});

it('fails cleanly on an invalid query, reporting the error on STDERR', function () {
    $result = dbQueryTester()->run(['query' => 'SELECT * FROM maho_dbquery_no_such_table', '--driver' => 'adapter']);

    $this->assertCommandFailed($result);
    expect($result->getErrorOutput())->toContain('maho_dbquery_no_such_table');
});

it('rejects an unknown --driver value on STDERR', function () {
    $result = dbQueryTester()->run(['query' => 'SELECT 1', '--driver' => 'bogus']);

    $this->assertCommandIsInvalid($result);
    expect($result->getErrorOutput())->toContain('Invalid --driver value');
});

it('refuses multiple statements on the adapter path instead of running only the first', function () {
    $result = dbQueryTester()->run(['query' => 'SELECT 1 AS a; SELECT 2 AS b', '--driver' => 'adapter']);

    $this->assertCommandIsInvalid($result);
    expect($result->getErrorOutput())->toContain('Multiple SQL statements');
});

it('does not treat a semicolon inside a string literal as multiple statements', function () {
    $result = dbQueryTester()->run(['query' => "SELECT 'a;b' AS semi, 1 AS n", '--driver' => 'adapter']);

    $this->assertCommandIsSuccessful($result);
    expect($result->getDisplay())->toContain('a;b');
});

it('refuses a result set with duplicate column names instead of dropping a column', function () {
    $result = dbQueryTester()->run(['query' => 'SELECT 1 AS id, 2 AS id', '--driver' => 'adapter']);

    $this->assertCommandFailed($result);
    expect($result->getErrorOutput())->toContain('duplicate or ambiguous column names');
});

it('detects multiple statements while ignoring literals, identifiers, and comments', function () {
    expect(dbQueryReflect('containsMultipleStatements', 'SELECT 1', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1;', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1; SELECT 2', 'mysql'))->toBeTrue()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 'a;b'", 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT "x;y"', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT `a;b`', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1 /* ; */ , 2', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'UPDATE a SET x=1; UPDATE b SET y=2', 'mysql'))->toBeTrue();
});

it('splits MySQL strings by quote escaping and only recognizes comments outside quotes', function () {
    expect(dbQueryReflect('containsMultipleStatements', "SELECT 'a\\\\'; SELECT 2", 'mysql'))->toBeTrue()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 'a\\';b'", 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 'it''s; fine'", 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT '/* ; */'; SELECT 2", 'mysql'))->toBeTrue()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT /*!40101 1; */ 2', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1; /* trailing comment */', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', '/* ordinary comment */', 'mysql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1; /*!40101 SET @x = 1 */', 'mysql'))->toBeTrue();
});

it('splits a MySQL multiQuery batch after an even run of backslashes', function () {
    $adapterClass = new ReflectionClass(\Maho\Db\Adapter\Pdo\Mysql::class);
    $adapter = $adapterClass->newInstanceWithoutConstructor();
    $split = new ReflectionMethod(\Maho\Db\Adapter\Pdo\Mysql::class, '_splitMultiQuery');

    expect($split->invoke($adapter, "SELECT 'a\\\\'; SELECT 2"))->toBe(["SELECT 'a\\\\'", 'SELECT 2']);
});

it('does not let comment markers inside a MySQL string hide later statement boundaries', function () {
    $adapterClass = new ReflectionClass(\Maho\Db\Adapter\Pdo\Mysql::class);
    $adapter = $adapterClass->newInstanceWithoutConstructor();
    $split = new ReflectionMethod(\Maho\Db\Adapter\Pdo\Mysql::class, '_splitMultiQuery');

    expect($split->invoke($adapter, "SELECT '/*'; SELECT 2"))->toBe(["SELECT '/*'", 'SELECT 2']);
});

it('keeps semicolons inside doubled-quote MySQL strings', function () {
    $adapterClass = new ReflectionClass(\Maho\Db\Adapter\Pdo\Mysql::class);
    $adapter = $adapterClass->newInstanceWithoutConstructor();
    $split = new ReflectionMethod(\Maho\Db\Adapter\Pdo\Mysql::class, '_splitMultiQuery');

    expect($split->invoke($adapter, "SELECT 'it''s; fine'; SELECT 2"))->toBe(["SELECT 'it''s; fine'", 'SELECT 2']);
});

it('does not emit ordinary trailing comments as adapter statements', function () {
    $adapters = [
        \Maho\Db\Adapter\Pdo\Mysql::class,
        \Maho\Db\Adapter\Pdo\Pgsql::class,
        \Maho\Db\Adapter\Pdo\Sqlite::class,
    ];

    foreach ($adapters as $adapterClass) {
        $adapter = new ReflectionClass($adapterClass)->newInstanceWithoutConstructor();
        $split = new ReflectionMethod($adapterClass, '_splitMultiQuery');
        expect($split->invoke($adapter, 'SELECT 1; /* trailing comment */'))->toBe(['SELECT 1'])
            ->and($split->invoke($adapter, '/* comment only */'))->toBe([]);
    }
});

it('uses PostgreSQL string and dollar-quote rules when detecting statement boundaries', function () {
    expect(dbQueryReflect('containsMultipleStatements', 'SELECT $$a;b$$', 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT $body$a;b$body$', 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 'a\\'; SELECT 2", 'pgsql'))->toBeTrue()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT E'a\\';b'", 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT $$a;b$$; SELECT 2', 'pgsql'))->toBeTrue()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 1--comment; still comment\n", 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1; /* trailing comment */', 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 1; -- trailing comment\n", 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', '/* ordinary comment */', 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1 /* ; */', 'pgsql'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', 'SELECT 1; /* trailing comment */', 'sqlite'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', "SELECT 1; -- trailing comment\n", 'sqlite'))->toBeFalse()
        ->and(dbQueryReflect('containsMultipleStatements', '/* ordinary comment */', 'sqlite'))->toBeFalse();
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

it('refuses an unsupported engine on the framework connection', function () {
    $output = new Symfony\Component\Console\Output\BufferedOutput();
    $status = dbQueryReflect('executeViaAdapter', $output, 'SELECT 1', 'postgres', false);

    expect($status)->toBe(Symfony\Component\Console\Command\Command::FAILURE);
});
