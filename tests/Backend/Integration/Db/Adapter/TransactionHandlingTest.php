<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->testTable = $this->adapter->getTableName('admin_user');
});

describe('Nested Transaction Handling', function () {
    it('starts and commits single transaction', function () {
        expect($this->adapter->getTransactionLevel())->toBe(0);

        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(1);

        $this->adapter->commit();
        expect($this->adapter->getTransactionLevel())->toBe(0);
    });

    it('handles nested transactions with proper counter', function () {
        expect($this->adapter->getTransactionLevel())->toBe(0);

        // Level 1
        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(1);

        // Level 2 (nested)
        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(2);

        // Level 3 (nested)
        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(3);

        // Commit level 3
        $this->adapter->commit();
        expect($this->adapter->getTransactionLevel())->toBe(2);

        // Commit level 2
        $this->adapter->commit();
        expect($this->adapter->getTransactionLevel())->toBe(1);

        // Commit level 1 (actual DB commit)
        $this->adapter->commit();
        expect($this->adapter->getTransactionLevel())->toBe(0);
    });

    it('only commits database at outermost level', function () {
        $initialCount = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->testTable}");

        $this->adapter->beginTransaction();
        $this->adapter->insert($this->testTable, [
            'username' => 'test_nested_' . uniqid(),
            'firstname' => 'Test',
            'lastname' => 'Nested',
            'email' => 'nested@test.com',
            'created' => Mage::app()->getLocale()->nowUtc(),
            'modified' => Mage::app()->getLocale()->nowUtc(),
            'is_active' => 1,
        ]);

        // Start nested transaction
        $this->adapter->beginTransaction();
        // Commit nested (should NOT commit to DB)
        $this->adapter->commit();

        // Data should still be in transaction
        expect($this->adapter->getTransactionLevel())->toBe(1);

        // Rollback outermost
        $this->adapter->rollBack();

        // Verify data was rolled back
        $finalCount = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->testTable}");
        expect($finalCount)->toBe($initialCount);
    });

    it('rolls back all nested transactions when outermost rolls back', function () {
        $initialCount = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->testTable}");

        $this->adapter->beginTransaction();

        $this->adapter->insert($this->testTable, [
            'username' => 'test_rollback_' . uniqid(),
            'firstname' => 'Test',
            'lastname' => 'Rollback',
            'email' => 'rollback@test.com',
            'created' => Mage::app()->getLocale()->nowUtc(),
            'modified' => Mage::app()->getLocale()->nowUtc(),
            'is_active' => 1,
        ]);

        // Nested transaction
        $this->adapter->beginTransaction();
        $this->adapter->commit(); // Just decrements counter

        // Rollback outermost
        $this->adapter->rollBack();

        expect($this->adapter->getTransactionLevel())->toBe(0);

        // Verify rollback worked
        $finalCount = $this->adapter->fetchOne("SELECT COUNT(*) FROM {$this->testTable}");
        expect($finalCount)->toBe($initialCount);
    });

    it('detects transaction state correctly with isTransaction', function () {
        expect($this->adapter->isTransaction())->toBeFalse();

        $this->adapter->beginTransaction();
        expect($this->adapter->isTransaction())->toBeTrue();

        $this->adapter->beginTransaction();
        expect($this->adapter->isTransaction())->toBeTrue();

        $this->adapter->commit();
        expect($this->adapter->isTransaction())->toBeTrue();

        $this->adapter->commit();
        expect($this->adapter->isTransaction())->toBeFalse();
    });
});

describe('Transaction Rollback Edge Cases', function () {
    it('handles rollback at level 0 gracefully', function () {
        expect($this->adapter->getTransactionLevel())->toBe(0);
        $this->adapter->rollBack(); // Should not throw
        expect($this->adapter->getTransactionLevel())->toBe(0);
    });

    it('handles commit at level 0 gracefully', function () {
        expect($this->adapter->getTransactionLevel())->toBe(0);
        $this->adapter->commit(); // Should not throw
        expect($this->adapter->getTransactionLevel())->toBe(0);
    });

    it('handles alternating begin/rollback correctly', function () {
        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(1);

        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(2);

        $this->adapter->rollBack();
        expect($this->adapter->getTransactionLevel())->toBe(1);

        $this->adapter->beginTransaction();
        expect($this->adapter->getTransactionLevel())->toBe(2);

        $this->adapter->commit();
        expect($this->adapter->getTransactionLevel())->toBe(1);

        $this->adapter->commit();
        expect($this->adapter->getTransactionLevel())->toBe(0);
    });
});

describe('Advisory Locks Inside Transactions', function () {
    // Regression: on SQLite, getLock() used to run "CREATE TABLE IF NOT EXISTS" (a DDL
    // statement) on every call. SQLite forbids DDL inside a transaction, so acquiring a
    // lock while a transaction was open threw "DDL statements are not allowed in
    // transactions" — even when the table already existed. This surfaced when saving a
    // config section (e.g. activating SMTP) as the misleading "Unable to save the cron
    // expression." error, because a config-section load acquires the cache-save lock
    // inside the save transaction.
    //
    // The fix creates the locks table once at connection init (outside any transaction),
    // so getLock() never issues DDL — even the very first acquisition of a process, and
    // even inside an open transaction, is safe.
    it('creates the SQLite locks table at connection init, before any lock is acquired', function () {
        if (!($this->adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite)) {
            expect(true)->toBeTrue(); // native advisory locks on MySQL/PostgreSQL — nothing to assert
            return;
        }

        // The connection is already established (beforeEach). Without ever calling
        // getLock(), the table must already exist thanks to _initConnection(). This is
        // what lets the first getLock() of a process run inside a transaction safely.
        $tableExists = $this->adapter->fetchOne(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='maho_advisory_locks'",
        );
        expect((bool) $tableExists)->toBeTrue();
    });

    it('acquires and releases an advisory lock while a transaction is open', function () {
        $lockName = 'maho_txn_lock_test';
        $this->adapter->releaseLock($lockName);

        $this->adapter->beginTransaction();
        try {
            $acquired = $this->adapter->getLock($lockName, 1);
            expect($acquired)->toBeTrue();
            expect($this->adapter->getTransactionLevel())->toBe(1);
        } finally {
            $this->adapter->releaseLock($lockName);
            $this->adapter->rollBack();
        }

        expect($this->adapter->getTransactionLevel())->toBe(0);
    });
});
