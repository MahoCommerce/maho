<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\DBQuery;
use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Adapter\Pdo\Sqlite;
use Maho\Db\Sql\StatementSplitter;

it('splits inert SQL fixtures according to each supported engine grammar', function () {
    $fixtures = [
        ['mysql', "SELECT 'a\\\\'; SELECT 2", ["SELECT 'a\\\\'", 'SELECT 2']],
        ['mysql', "SELECT 'a\\';b'", ["SELECT 'a\\';b'"]],
        ['mysql', "SELECT 'it''s; fine'", ["SELECT 'it''s; fine'"]],
        ['mysql', "SELECT '/* ; */'; SELECT 2", ["SELECT '/* ; */'", 'SELECT 2']],
        ['mysql', 'SELECT /*!40101 1; */ 2', ['SELECT /*!40101 1; */ 2']],
        ['mysql', '/* ordinary comment */', []],
        ['mysql', 'SELECT 1; /* trailing comment */', ['SELECT 1']],
        ['mysql', "SELECT 1; -- trailing comment\n", ['SELECT 1']],
        ['mysql', '/*!40101 SET @lexer_probe = 1 */', ['/*!40101 SET @lexer_probe = 1 */']],
        ['mysql', 'SELECT 1; /*!40101 SET @lexer_probe = 1 */', [
            'SELECT 1',
            '/*!40101 SET @lexer_probe = 1 */',
        ]],
        ['mysql', '/* leading */ SELECT 1', ['/* leading */ SELECT 1']],
        ['mysql', 'SELECT 1 /* ; */; SELECT 2', ['SELECT 1 /* ; */', 'SELECT 2']],
        ['pgsql', 'SELECT $$a;b$$', ['SELECT $$a;b$$']],
        ['pgsql', 'SELECT $body$a;b$body$', ['SELECT $body$a;b$body$']],
        ['pgsql', "SELECT 'a\\'; SELECT 2", ["SELECT 'a\\'", 'SELECT 2']],
        ['pgsql', "SELECT E'a\\';b'", ["SELECT E'a\\';b'"]],
        ['pgsql', 'SELECT $$a;b$$; SELECT 2', ['SELECT $$a;b$$', 'SELECT 2']],
        ['pgsql', 'SELECT 1 /* outer /* nested ; */ still comment */; SELECT 2', [
            'SELECT 1 /* outer /* nested ; */ still comment */',
            'SELECT 2',
        ]],
        ['pgsql', 'SELECT foo$tag$; SELECT 2', ['SELECT foo$tag$', 'SELECT 2']],
        ['pgsql', "SELECT 1--comment; still comment\n", ['SELECT 1--comment; still comment']],
        ['pgsql', '/* ordinary comment */', []],
        ['pgsql', 'SELECT 1; /* trailing comment */', ['SELECT 1']],
        ['pgsql', "SELECT 1; -- trailing comment\n", ['SELECT 1']],
        ['pgsql', '/* leading */ SELECT 1', ['/* leading */ SELECT 1']],
        ['pgsql', "SELECT 1 -- comment\r; SELECT 2", ['SELECT 1 -- comment', 'SELECT 2']],
        ['pgsql', "SELECT 1 -- comment\r\n; SELECT 2", ['SELECT 1 -- comment', 'SELECT 2']],
        ['pgsql', "SELECT 1; -- trailing comment\r", ['SELECT 1']],
        ['pgsql', "SELECT 1; -- trailing comment\r\n", ['SELECT 1']],
        ['pgsql', "-- comment only\r", []],
        ['pgsql', "-- comment only\r\n", []],
        ['pgsql', "-- leading comment\rSELECT 1; SELECT 2", ["-- leading comment\rSELECT 1", 'SELECT 2']],
        ['sqlite', "SELECT 'it''s; fine'", ["SELECT 'it''s; fine'"]],
        ['sqlite', '/* ordinary comment */', []],
        ['sqlite', 'SELECT 1; /* trailing comment */', ['SELECT 1']],
        ['sqlite', "SELECT 1; -- trailing comment\n", ['SELECT 1']],
        ['sqlite', '/* leading */ SELECT 1', ['/* leading */ SELECT 1']],
        ['sqlite', 'SELECT [semi;colon]; SELECT 2', ['SELECT [semi;colon]', 'SELECT 2']],
        ['sqlite', 'SELECT 1; SELECT 2', ['SELECT 1', 'SELECT 2']],
    ];

    foreach ($fixtures as [$engine, $sql, $expected]) {
        expect(StatementSplitter::split($sql, $engine))->toBe($expected);
    }
});

it('routes each PDO adapter multiQuery splitter through its engine grammar without connecting', function () {
    $adapters = [
        'mysql' => Mysql::class,
        'pgsql' => Pgsql::class,
        'sqlite' => Sqlite::class,
    ];

    foreach ($adapters as $engine => $adapterClass) {
        $adapter = new ReflectionClass($adapterClass)->newInstanceWithoutConstructor();
        $split = new ReflectionMethod($adapterClass, '_splitMultiQuery');
        $fixture = $engine === 'pgsql' ? 'SELECT $$a;b$$; SELECT 2' : 'SELECT 1; SELECT 2';
        expect($split->invoke($adapter, $fixture))->toHaveCount(2)
            ->and($split->invoke($adapter, 'SELECT 1; /* trailing comment */'))->toBe(['SELECT 1'])
            ->and($split->invoke($adapter, '/* ordinary comment */'))->toBe([]);

        if ($engine === 'pgsql') {
            expect($split->invoke($adapter, "SELECT 1 -- comment\r; SELECT 2"))->toHaveCount(2);
        }
    }
});

it('routes the CLI single-statement guard through the selected engine grammar', function () {
    $query = new DBQuery('db:query');
    $split = new ReflectionMethod(DBQuery::class, 'splitSqlStatements');

    expect($split->invoke($query, 'SELECT $$a;b$$', 'pgsql'))->toHaveCount(1)
        ->and($split->invoke($query, 'SELECT $$a;b$$; SELECT 2', 'pgsql'))->toHaveCount(2)
        ->and($split->invoke($query, "SELECT 'a\\'; SELECT 2", 'pgsql'))->toHaveCount(2)
        ->and($split->invoke($query, "SELECT 1 -- comment\r; SELECT 2", 'pgsql'))->toHaveCount(2);
});

it('recognizes PostgreSQL dollar tags with non-ASCII identifier characters', function () {
    expect(StatementSplitter::split('SELECT $é$a;b$é$; SELECT 2', 'pgsql'))
        ->toBe(['SELECT $é$a;b$é$', 'SELECT 2'])
        ->and(StatementSplitter::split('SELECT $étag_2$a;b$étag_2$', 'pgsql'))
        ->toBe(['SELECT $étag_2$a;b$étag_2$'])
        ->and(StatementSplitter::split('SELECT café$tag$; SELECT 2', 'pgsql'))
        ->toBe(['SELECT café$tag$', 'SELECT 2']);
});

it('keeps PostgreSQL escape string prefixes separate from non-ASCII identifiers', function () {
    expect(StatementSplitter::split("SELECT éE'a\\'; SELECT 2", 'pgsql'))
        ->toBe(["SELECT éE'a\\'", 'SELECT 2'])
        ->and(StatementSplitter::split("SELECT ée'a\\'; SELECT 2", 'pgsql'))
        ->toBe(["SELECT ée'a\\'", 'SELECT 2'])
        ->and(StatementSplitter::split("SELECT E'a\\';b'; SELECT 2", 'pgsql'))
        ->toBe(["SELECT E'a\\';b'", 'SELECT 2']);
});

it('rejects an engine that it has no grammar for', function () {
    expect(fn() => StatementSplitter::split('SELECT 1', 'postgres'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported database engine: postgres');
});
