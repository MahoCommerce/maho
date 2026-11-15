<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Driver\Sqlite;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * Prepares statements on the native PDO connection
 *
 * The DBAL PDO statement and result keep their PDOStatement private, and the declared type
 * of a column is only available from PDOStatement::getColumnMeta(). So this connection
 * prepares and runs every statement itself. It also starts each transaction with BEGIN
 * IMMEDIATE. Everything else goes to the wrapped connection.
 */
final class Connection extends AbstractConnectionMiddleware
{
    /**
     * Take the write lock when the transaction starts.
     *
     * A deferred transaction that reads first cannot write after another connection wrote in
     * between: SQLite returns "database is locked" at once, and the busy timeout does not
     * help. An immediate transaction waits for the lock at BEGIN, within the busy timeout, and
     * then always finishes. pdo_sqlite asks SQLite whether a transaction is open, so
     * PDO::commit() and PDO::rollBack() still work.
     */
    #[\Override]
    public function beginTransaction(): void
    {
        $this->exec('BEGIN IMMEDIATE');
    }

    #[\Override]
    public function prepare(string $sql): Statement
    {
        try {
            return new Statement($this->getPdo()->prepare($sql));
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
    }

    #[\Override]
    public function query(string $sql): Result
    {
        try {
            $statement = $this->getPdo()->query($sql);
        } catch (\PDOException $e) {
            throw Exception::new($e);
        }
        return new Result($statement);
    }

    private function getPdo(): \PDO
    {
        $pdo = $this->getNativeConnection();
        assert($pdo instanceof \PDO);
        return $pdo;
    }
}
