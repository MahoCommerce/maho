<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\AdapterInterface;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->testTable = $this->adapter->getTableName('core_config_data');
});

describe('Parameter Binding and SQL Injection Protection', function () {
    it('properly binds single parameter with question mark placeholder', function () {
        $value = "'OR'1'='1";

        $result = $this->adapter->fetchOne(
            "SELECT scope FROM {$this->testTable} WHERE scope = ? LIMIT 1",
            [$value],
        );

        // Should return false (no match) instead of SQL injection
        expect($result)->toBeFalse();
    });

    it('properly binds multiple parameters', function () {
        $scope = 'default';
        $scopeId = 0;

        $result = $this->adapter->fetchAll(
            "SELECT * FROM {$this->testTable} WHERE scope = ? AND scope_id = ? LIMIT 5",
            [$scope, $scopeId],
        );

        expect($result)->toBeArray();
        foreach ($result as $row) {
            expect($row['scope'])->toBe('default');
            expect((int) $row['scope_id'])->toBe(0);
        }
    });

    it('handles special characters in parameters safely', function () {
        $maliciousStrings = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT NULL--",
            "<script>alert('xss')</script>",
            '../../etc/passwd',
        ];

        foreach ($maliciousStrings as $malicious) {
            $result = $this->adapter->fetchOne(
                "SELECT COUNT(*) FROM {$this->testTable} WHERE path = ?",
                [$malicious],
            );

            // Should safely return 0, not execute injection (Doctrine may return 0 or '0')
            expect((int) $result)->toBe(0);
        }
    });

    it('properly quotes values in quoteInto', function () {
        $malicious = "' OR '1'='1";
        $sql = $this->adapter->quoteInto(
            "SELECT * FROM {$this->testTable} WHERE path = ?",
            $malicious,
        );

        // Should contain escaped value - MySQL uses \', PostgreSQL uses ''
        // Both should properly escape the malicious input
        expect($sql)->toMatch("/\\\\'|''/"); // Either \' (MySQL) or '' (PostgreSQL)
        expect($sql)->not->toContain("' OR '1'='1");
    });

    it('handles array parameters for IN clauses', function () {
        $scopes = ['default', 'websites', 'stores'];

        $result = $this->adapter->fetchAll(
            "SELECT DISTINCT scope FROM {$this->testTable} WHERE scope IN (?)",
            [$scopes],
        );

        expect($result)->toBeArray();
        $returnedScopes = array_column($result, 'scope');
        expect($returnedScopes)->toBeArray();
    });

    it('handles NULL values correctly', function () {
        $result = $this->adapter->fetchOne(
            'SELECT ? as test_null',
            [null],
        );

        expect($result)->toBeNull();
    });

    it('handles numeric types correctly', function () {
        $int = 42;
        $float = 3.14159;

        $result = $this->adapter->fetchRow(
            'SELECT ? as int_val, ? as float_val',
            [$int, $float],
        );

        // Doctrine may return numbers as strings or integers - both are valid
        expect((int) $result['int_val'])->toBe(42);
        $floatVal = (float) $result['float_val'];
        expect($floatVal)->toBeGreaterThan(3.14);
        expect($floatVal)->toBeLessThan(3.15);
    });

    it('prevents second-order SQL injection', function () {
        // Insert data containing SQL-like strings
        $testPath = "test/injection/'" . uniqid();
        $testValue = "'; DELETE FROM admin_user WHERE '1'='1";

        $this->adapter->insert($this->testTable, [
            'scope' => 'test',
            'scope_id' => 999999,
            'path' => $testPath,
            'value' => $testValue,
        ]);

        // Retrieve and use in another query (should be safe)
        $retrieved = $this->adapter->fetchOne(
            "SELECT value FROM {$this->testTable} WHERE path = ?",
            [$testPath],
        );

        expect($retrieved)->toBe($testValue);

        // Use retrieved value in another query (should not inject)
        $result = $this->adapter->fetchOne(
            "SELECT COUNT(*) FROM {$this->testTable} WHERE value = ?",
            [$retrieved],
        );

        expect((int) $result)->toBe(1);

        // Cleanup
        $this->adapter->delete($this->testTable, 'path = ' . $this->adapter->quote($testPath));
    });
});

describe('Insert/Update/Delete Parameter Binding', function () {
    it('safely inserts data with special characters', function () {
        $uniquePath = 'test/special/' . uniqid();
        $specialValue = "'; DROP TABLE admin_user; --";

        $affected = $this->adapter->insert($this->testTable, [
            'scope' => 'test',
            'scope_id' => 999998,
            'path' => $uniquePath,
            'value' => $specialValue,
        ]);

        expect($affected)->toBe(1);

        // Verify safe insertion
        $result = $this->adapter->fetchOne(
            "SELECT value FROM {$this->testTable} WHERE path = ?",
            [$uniquePath],
        );

        expect($result)->toBe($specialValue);

        // Cleanup
        $this->adapter->delete($this->testTable, $this->adapter->quoteInto('path = ?', $uniquePath));
    });

    it('safely updates data with parameters', function () {
        $uniquePath = 'test/update/' . uniqid();

        $this->adapter->insert($this->testTable, [
            'scope' => 'test',
            'scope_id' => 999997,
            'path' => $uniquePath,
            'value' => 'original',
        ]);

        $maliciousUpdate = "'; UPDATE admin_user SET is_active=0; --";

        $affected = $this->adapter->update(
            $this->testTable,
            ['value' => $maliciousUpdate],
            $this->adapter->quoteInto('path = ?', $uniquePath),
        );

        expect($affected)->toBe(1);

        // Verify safe update
        $result = $this->adapter->fetchOne(
            "SELECT value FROM {$this->testTable} WHERE path = ?",
            [$uniquePath],
        );

        expect($result)->toBe($maliciousUpdate);

        // Cleanup
        $this->adapter->delete($this->testTable, $this->adapter->quoteInto('path = ?', $uniquePath));
    });

    it('safely deletes with parameter binding', function () {
        $uniquePath = 'test/delete/' . uniqid();

        $this->adapter->insert($this->testTable, [
            'scope' => 'test',
            'scope_id' => 999996,
            'path' => $uniquePath,
            'value' => 'to_delete',
        ]);

        // Delete using WHERE with binding
        $affected = $this->adapter->delete(
            $this->testTable,
            $this->adapter->quoteInto('path = ?', $uniquePath),
        );

        expect($affected)->toBe(1);

        // Verify deletion
        $result = $this->adapter->fetchOne(
            "SELECT COUNT(*) FROM {$this->testTable} WHERE path = ?",
            [$uniquePath],
        );

        expect((int) $result)->toBe(0);
    });
});

describe('Quote Methods', function () {
    it('quotes identifiers safely', function () {
        $tableName = 'users';
        $quoted = $this->adapter->quoteIdentifier($tableName);

        // MySQL uses backticks, PostgreSQL and SQLite use double quotes
        $q = $this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql ? '`' : '"';
        expect($quoted)->toBe("{$q}users{$q}");
    });

    it('quotes column names with aliases', function () {
        $column = 'user_id';
        $alias = 'id';

        $quoted = $this->adapter->quoteColumnAs($column, $alias);

        // MySQL uses backticks, PostgreSQL and SQLite use double quotes
        $q = $this->adapter instanceof \Maho\Db\Adapter\Pdo\Mysql ? '`' : '"';
        expect($quoted)->toContain("{$q}user_id{$q}");
        expect($quoted)->toContain('AS');
        expect($quoted)->toContain("{$q}id{$q}");
    });

    it('quotes expressions correctly', function () {
        $expr = new Maho\Db\Expr('COUNT(*)');
        $quoted = $this->adapter->quote($expr);

        // Expressions should not be quoted
        expect($quoted)->toBe('COUNT(*)');
    });

    it('quotes arrays for IN clauses', function () {
        $values = [1, 2, 3, null];
        $quoted = $this->adapter->quote($values);

        expect($quoted)->toContain('1');
        expect($quoted)->toContain('2');
        expect($quoted)->toContain('3');
        expect($quoted)->toContain('NULL');
    });
});
