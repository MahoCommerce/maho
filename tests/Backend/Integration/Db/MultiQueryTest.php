<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Ddl\Table;

uses(Tests\MahoBackendTestCase::class);

/**
 * multiQuery() runs the batch of a setup script on the real database, one statement at a time.
 * Each batch puts semicolons where the splitter must not split.
 */

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->tableName = 'test_multi_query_' . uniqid();

    $table = $this->adapter->newTable($this->tableName)
        ->addColumn('id', Table::TYPE_INTEGER, null, ['nullable' => false, 'primary' => true])
        ->addColumn('val', Table::TYPE_VARCHAR, 64, ['nullable' => false]);
    $this->adapter->createTable($table);
});

afterEach(function () {
    if ($this->adapter->isTableExists($this->tableName)) {
        $this->adapter->dropTable($this->tableName);
    }
});

function multiQueryValues(object $test): array
{
    return $test->adapter->fetchPairs("SELECT id, val FROM {$test->tableName} ORDER BY id");
}

it('runs each statement of a batch with semicolons in values and comments', function () {
    $common = <<<'SQL'
        INSERT INTO {t} (id, val) VALUES (1, 'a;b');
        -- note; with a semicolon
        INSERT INTO {t} (id, val) VALUES (2, 'it''s; fine'); /* trailing; comment */
        INSERT INTO {t} (id, val) VALUES (3, '/* ; */');

        SQL;
    [$engineSql, $engineValues] = match (true) {
        $this->adapter instanceof Mysql => [<<<'SQL'
            # hash; comment
            INSERT INTO {t} (id, val) VALUES (4, 'C:\\');
            DELIMITER //
            INSERT INTO {t} (id, val) VALUES (5, 'p;q')//
            DELIMITER ;
            INSERT INTO {t} (id, val) VALUES (6, 'r');
            SQL, [4 => 'C:\\', 5 => 'p;q', 6 => 'r']],
        $this->adapter instanceof Pgsql => [<<<'SQL'
            INSERT INTO {t} (id, val) VALUES (4, 'C:\');
            INSERT INTO {t} (id, val) VALUES (5, $$p;q$$);
            INSERT INTO {t} (id, val) VALUES (6, E'r\';s');
            SQL, [4 => 'C:\\', 5 => 'p;q', 6 => "r';s"]],
        default => [<<<'SQL'
            INSERT INTO {t} (id, val) VALUES (4, 'C:\');
            INSERT INTO {t} (id, [val]) VALUES (5, 'p;q');
            SQL, [4 => 'C:\\', 5 => 'p;q']],
    };

    $result = $this->adapter->multiQuery(str_replace('{t}', $this->tableName, $common . $engineSql));

    expect($result)->toHaveCount(3 + count($engineValues))
        ->and(multiQueryValues($this))->toEqual([1 => 'a;b', 2 => "it's; fine", 3 => '/* ; */'] + $engineValues);
});

it('reads a MySQL batch by the session sql_mode', function () {
    if (!$this->adapter instanceof Mysql) {
        $this->markTestSkipped('sql_mode is a MySQL setting.');
    }

    $sqlMode = $this->adapter->getSessionSqlMode();
    $this->adapter->query('SET SESSION sql_mode = ' . $this->adapter->quote(ltrim("{$sqlMode},NO_BACKSLASH_ESCAPES", ',')));
    try {
        $this->adapter->multiQuery(
            "INSERT INTO {$this->tableName} (id, val) VALUES (1, 'C:\\'); INSERT INTO {$this->tableName} (id, val) VALUES (2, 'x')",
        );
    } finally {
        $this->adapter->query('SET SESSION sql_mode = ' . $this->adapter->quote($sqlMode));
    }

    expect(multiQueryValues($this))->toEqual([1 => 'C:\\', 2 => 'x']);
});
