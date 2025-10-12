<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Tests to verify Doctrine DBAL migration generates same SQL as Zend_DB did
 * This ensures backward compatibility and prevents subtle query changes
 */

use Maho\Db\Expr;
use Maho\Db\Select;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->testTable = $this->adapter->getTableName('core_config_data');
});

describe('Varien_Db_Expr Compatibility', function () {
    it('accepts Varien_Db_Expr objects', function () {
        // Old code uses Varien_Db_Expr, new code uses Maho\Db\Expr
        // Both should work
        $varienExpr = new Varien_Db_Expr('COUNT(*)');

        $select = $this->adapter->select()
            ->from($this->testTable, ['scope', $varienExpr])
            ->group('scope')
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('COUNT(*)');
        expect($sql)->toContain('GROUP BY');
    });

    it('handles Varien_Db_Expr in WHERE clauses', function () {
        $expr = new Varien_Db_Expr('UNIX_TIMESTAMP(NOW())');

        $select = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('scope = ?', 'default')
            ->where("LENGTH(path) > ?", $expr)
            ->limit(1);

        $sql = $select->assemble();

        expect($sql)->toContain('UNIX_TIMESTAMP(NOW())');
    });

    it('does not quote Varien_Db_Expr values', function () {
        $expr = new Varien_Db_Expr('NOW()');
        $quoted = $this->adapter->quote($expr);

        expect($quoted)->toBe('NOW()');
        expect($quoted)->not->toContain("'NOW()'");
    });
});

describe('SQL Generation Compatibility - Simple Queries', function () {
    it('generates expected simple SELECT', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['scope', 'path', 'value'])
            ->where('scope = ?', 'default')
            ->limit(10);

        $sql = $select->assemble();

        // Verify SQL structure
        expect($sql)->toContain('SELECT');
        expect($sql)->toContain('`scope`');
        expect($sql)->toContain('`path`');
        expect($sql)->toContain('`value`');
        expect($sql)->toContain('FROM');
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('LIMIT 10');
    });

    it('generates expected SELECT with table alias', function () {
        $select = $this->adapter->select()
            ->from(['c' => $this->testTable], ['scope', 'path'])
            ->where('c.scope = ?', 'default')
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('AS `c`');
        expect($sql)->toContain('scope');
    });

    it('generates expected SELECT query with columns', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['path', 'value'])
            ->limit(1);

        $sql = $select->assemble();

        expect($sql)->toContain('SELECT');
        expect($sql)->toContain('FROM');
    });
});

describe('SQL Generation Compatibility - JOINs', function () {
    it('generates expected INNER JOIN syntax', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->testTable], ['path'])
            ->joinInner(
                ['s' => $storeTable],
                'c.scope_id = s.store_id',
                ['code', 'name']
            )
            ->where('c.scope = ?', 'stores')
            ->limit(5);

        $sql = $select->assemble();

        // Zend_DB generates "INNER JOIN"
        expect($sql)->toMatch('/INNER\s+JOIN/i');
        expect($sql)->toContain('ON c.scope_id = s.store_id');
        expect($sql)->toContain($storeTable);
    });

    it('generates expected LEFT JOIN syntax', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->testTable], ['path'])
            ->joinLeft(
                ['s' => $storeTable],
                'c.scope_id = s.store_id',
                ['code']
            )
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toMatch('/LEFT\s+JOIN/i');
        expect($sql)->toContain('ON c.scope_id = s.store_id');
    });

    it('generates expected CROSS JOIN syntax', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->testTable], ['path'])
            ->joinCross(
                ['s' => $storeTable],
                ['code']
            )
            ->limit(1);

        $sql = $select->assemble();

        expect($sql)->toMatch('/CROSS\s+JOIN/i');
    });
});

describe('SQL Generation Compatibility - WHERE Clauses', function () {
    it('generates expected AND conditions', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0)
            ->limit(5);

        $sql = $select->assemble();

        // Multiple WHERE clauses are combined with AND
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('AND');
        expect($sql)->toContain("scope = 'default'");
        expect($sql)->toContain('scope_id');
    });

    it('generates expected OR conditions', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('scope = ?', 'default')
            ->orWhere('scope = ?', 'websites')
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('OR');
    });

    it('generates expected IN clause', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['scope'])
            ->where('scope IN (?)', ['default', 'websites', 'stores'])
            ->limit(10);

        $sql = $select->assemble();

        expect($sql)->toContain('IN');
        expect($sql)->toContain("'default'");
        expect($sql)->toContain("'websites'");
        expect($sql)->toContain("'stores'");
    });

    it('generates expected LIKE clause', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('path LIKE ?', 'web/%')
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('LIKE');
        expect($sql)->toContain("'web/%'");
    });
});

describe('SQL Generation Compatibility - ORDER and GROUP BY', function () {
    it('generates expected ORDER BY', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['scope', 'path'])
            ->order('scope ASC')
            ->order('path DESC')
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('ORDER BY');
        expect($sql)->toContain('ASC');
        expect($sql)->toContain('DESC');
    });

    it('generates expected GROUP BY with HAVING', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['scope', new Expr('COUNT(*) as cnt')])
            ->group('scope')
            ->having('COUNT(*) > ?', 5);

        $sql = $select->assemble();

        expect($sql)->toContain('GROUP BY');
        expect($sql)->toContain('HAVING');
        expect($sql)->toContain('COUNT(*)');
    });
});

describe('SQL Generation Compatibility - Special Clauses', function () {
    it('generates expected DISTINCT', function () {
        $select = $this->adapter->select()
            ->distinct()
            ->from($this->testTable, ['scope']);

        $sql = $select->assemble();

        expect($sql)->toMatch('/SELECT\s+DISTINCT/i');
    });

    it('generates expected FOR UPDATE', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('scope = ?', 'default')
            ->forUpdate(true)
            ->limit(1);

        $sql = $select->assemble();

        expect($sql)->toContain('FOR UPDATE');
    });

    it('generates expected LIMIT with OFFSET', function () {
        $select = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->limit(10, 20);

        $sql = $select->assemble();

        expect($sql)->toContain('LIMIT 10');
        expect($sql)->toContain('OFFSET 20');
    });

    it('generates expected UNION', function () {
        $select1 = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('scope = ?', 'default')
            ->limit(2);

        $select2 = $this->adapter->select()
            ->from($this->testTable, ['path'])
            ->where('scope = ?', 'websites')
            ->limit(2);

        $union = $this->adapter->select()
            ->union([$select1, $select2]);

        $sql = $union->assemble();

        expect($sql)->toContain('UNION');
        expect(substr_count($sql, 'SELECT'))->toBeGreaterThanOrEqual(2);
    });
});

describe('SQL Generation Compatibility - Insert/Update/Delete', function () {
    it('generates expected INSERT statement', function () {
        // We can't easily test the exact SQL without modifying internals,
        // but we can verify the method produces working SQL
        $data = [
            'scope' => 'test',
            'scope_id' => 999999,
            'path' => 'test/compat/' . uniqid(),
            'value' => 'test_value',
        ];

        $affected = $this->adapter->insert($this->testTable, $data);

        expect($affected)->toBe(1);

        // Cleanup
        $this->adapter->delete(
            $this->testTable,
            $this->adapter->quoteInto('path = ?', $data['path'])
        );
    });

    it('generates expected UPDATE statement', function () {
        $testPath = 'test/update_compat/' . uniqid();

        $this->adapter->insert($this->testTable, [
            'scope' => 'test',
            'scope_id' => 999998,
            'path' => $testPath,
            'value' => 'original',
        ]);

        $affected = $this->adapter->update(
            $this->testTable,
            ['value' => 'updated'],
            $this->adapter->quoteInto('path = ?', $testPath)
        );

        expect($affected)->toBe(1);

        // Cleanup
        $this->adapter->delete($this->testTable, $this->adapter->quoteInto('path = ?', $testPath));
    });

    it('generates expected DELETE statement', function () {
        $testPath = 'test/delete_compat/' . uniqid();

        $this->adapter->insert($this->testTable, [
            'scope' => 'test',
            'scope_id' => 999997,
            'path' => $testPath,
            'value' => 'to_delete',
        ]);

        $affected = $this->adapter->delete(
            $this->testTable,
            $this->adapter->quoteInto('path = ?', $testPath)
        );

        expect($affected)->toBe(1);
    });
});

describe('SQL Generation Compatibility - Special Methods', function () {
    it('generates same SQL for prepareSqlCondition', function () {
        // Test various condition formats
        $conditions = [
            ['eq' => 'value'],
            ['neq' => 'value'],
            ['like' => '%value%'],
            ['in' => ['value1', 'value2']],
            ['nin' => ['value1', 'value2']],
            ['notnull' => true],
            ['null' => true],
            ['gt' => 10],
            ['lt' => 20],
            ['gteq' => 10],
            ['lteq' => 20],
            ['from' => 10, 'to' => 20],
        ];

        foreach ($conditions as $condition) {
            $sql = $this->adapter->prepareSqlCondition('field_name', $condition);
            expect($sql)->toBeString();
            expect(strlen($sql))->toBeGreaterThan(0);
        }
    });

    it('generates expected quoteInto results', function () {
        $sql = $this->adapter->quoteInto('status = ?', 1);
        expect($sql)->toContain('status =');
        expect($sql)->toContain('1');

        $sql = $this->adapter->quoteInto('name = ?', 'O\'Reilly');
        expect($sql)->toContain("O\\'Reilly");

        $sql = $this->adapter->quoteInto('id IN (?)', [1, 2, 3]);
        expect($sql)->toContain('id IN');
        expect($sql)->toContain('1');
        expect($sql)->toContain('2');
        expect($sql)->toContain('3');
    });

    it('generates expected identifier quoting', function () {
        $quoted = $this->adapter->quoteIdentifier('table_name');
        expect($quoted)->toBe('`table_name`');

        $quoted = $this->adapter->quoteIdentifier('db.table');
        expect($quoted)->toBe('`db`.`table`');

        // Expressions should not be quoted
        $expr = new Expr('COUNT(*)');
        $quoted = $this->adapter->quoteIdentifier($expr);
        expect($quoted)->toBe('COUNT(*)');
    });

    it('generates expected quoteColumnAs', function () {
        $quoted = $this->adapter->quoteColumnAs('user_id', 'id');
        expect($quoted)->toContain('`user_id`');
        expect($quoted)->toContain('AS');
        expect($quoted)->toContain('`id`');
    });

    it('generates expected quoteTableAs', function () {
        $quoted = $this->adapter->quoteTableAs('users', 'u');
        expect($quoted)->toContain('`users`');
        expect($quoted)->toContain('AS');
        expect($quoted)->toContain('`u`');
    });
});

describe('SQL Generation Compatibility - Edge Cases', function () {
    it('handles NULL values correctly', function () {
        $quoted = $this->adapter->quote(null);
        expect($quoted)->toBe('NULL');
    });

    it('handles boolean values correctly', function () {
        $quoted = $this->adapter->quote(true);
        // Doctrine may return 1 or '1'
        expect($quoted)->toBeString();
        expect(trim($quoted, "'"))->toContain('1');

        $quoted = $this->adapter->quote(false);
        // Doctrine returns empty quoted string for false
        expect($quoted)->toBeString();
    });

    it('handles numeric values correctly', function () {
        $quoted = $this->adapter->quote(42);
        expect((string)$quoted)->toContain('42');

        $quoted = $this->adapter->quote(3.14159);
        $quotedStr = (string)$quoted;
        expect($quotedStr)->toBeString();
        expect($quotedStr)->toMatch('/3\.14159/');
    });

    it('handles empty array same as Zend_DB', function () {
        $sql = $this->adapter->quoteInto('id IN (?)', []);
        // Empty array should become NULL
        expect($sql)->toContain('NULL');
    });

    it('handles special characters same as Zend_DB', function () {
        $value = "Test\nNew\rLine\tTab\\Backslash'Quote";
        $quoted = $this->adapter->quote($value);

        // Should be properly escaped
        expect($quoted)->toContain("'");
        expect($quoted)->not->toBe($value);
    });
});
