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
            'created' => Mage_Core_Model_Locale::now(),
            'modified' => Mage_Core_Model_Locale::now(),
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
            'created' => Mage_Core_Model_Locale::now(),
            'modified' => Mage_Core_Model_Locale::now(),
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
