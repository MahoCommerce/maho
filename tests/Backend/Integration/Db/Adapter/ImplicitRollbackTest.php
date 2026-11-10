<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Exception\DeadlockException;
use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!($this->adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: the server error codes are MySQL and MariaDB codes.');
    }
});

afterEach(function () {
    if ($this->adapter instanceof Mysql) {
        $this->adapter->query('DROP PROCEDURE IF EXISTS test_raise_error');
    }
});

it('drops every transaction level when the server rolls the transaction back', function (int $errorCode, string $sqlState) {
    // SIGNAL gives the same server error on each run, with no second connection that races.
    $this->adapter->query('DROP PROCEDURE IF EXISTS test_raise_error');
    $this->adapter->query(
        "CREATE PROCEDURE test_raise_error() SIGNAL SQLSTATE '{$sqlState}' SET MYSQL_ERRNO = {$errorCode}",
    );

    $this->adapter->beginTransaction();
    $this->adapter->beginTransaction();

    expect(fn() => $this->adapter->query('CALL test_raise_error()'))->toThrow(DeadlockException::class);
    expect($this->adapter->getTransactionLevel())->toBe(0);
})->with([
    'deadlock' => [1213, '40001'],
    'MariaDB snapshot isolation conflict' => [1020, 'HY000'],
]);

it('keeps the transaction open after a lock wait timeout', function () {
    // With the default innodb_rollback_on_timeout=OFF, the server rolls back only the statement.
    $this->adapter->query('DROP PROCEDURE IF EXISTS test_raise_error');
    $this->adapter->query("CREATE PROCEDURE test_raise_error() SIGNAL SQLSTATE 'HY000' SET MYSQL_ERRNO = 1205");

    $this->adapter->beginTransaction();
    $this->adapter->beginTransaction();

    expect(fn() => $this->adapter->query('CALL test_raise_error()'))->toThrow(Exception::class);
    expect($this->adapter->getTransactionLevel())->toBe(2);

    $this->adapter->rollBack();
    $this->adapter->rollBack();
});
