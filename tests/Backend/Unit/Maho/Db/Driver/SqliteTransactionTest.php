<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Sqlite;

// Two connections to one database file, as two PHP processes of one store have.
beforeEach(function (): void {
    if (!extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is not installed');
    }
    $this->file = tempnam(sys_get_temp_dir(), 'maho_sqlite_transaction_');
    $this->first = new Sqlite(['path' => $this->file]);
    $this->second = new Sqlite(['path' => $this->file]);
    $this->first->query('CREATE TABLE note (id INTEGER PRIMARY KEY, body TEXT)');
    // The second connection gives up at once, so the test does not wait for the first one
    $this->second->query('PRAGMA busy_timeout = 0');
});

afterEach(function (): void {
    if (!isset($this->file)) {
        return;
    }
    $this->first->closeConnection();
    $this->second->closeConnection();
    foreach (['', '-wal', '-shm'] as $suffix) {
        @unlink($this->file . $suffix);
    }
});

it('lets a transaction that read first still write after another connection tried to write', function (): void {
    $this->first->beginTransaction();
    $this->first->fetchOne('SELECT COUNT(*) FROM note');

    // A deferred transaction let this write through, and then the write of the first
    // connection failed with "database is locked"
    expect(fn() => $this->second->insert('note', ['body' => 'second']))
        ->toThrow(Exception::class, 'database is locked');

    $this->first->insert('note', ['body' => 'first']);
    $this->first->commit();

    expect($this->second->fetchCol('SELECT body FROM note'))->toBe(['first']);
});

it('rolls back a transaction and frees the write lock', function (): void {
    $this->first->beginTransaction();
    $this->first->insert('note', ['body' => 'first']);
    $this->first->rollBack();

    $this->second->insert('note', ['body' => 'second']);

    expect($this->first->fetchCol('SELECT body FROM note'))->toBe(['second']);
});

it('keeps nested transactions in one transaction of the database', function (): void {
    $this->first->beginTransaction();
    $this->first->beginTransaction();
    $this->first->insert('note', ['body' => 'inner']);
    $this->first->commit();

    expect(fn() => $this->second->insert('note', ['body' => 'second']))
        ->toThrow(Exception::class, 'database is locked');

    $this->first->commit();

    expect($this->second->fetchCol('SELECT body FROM note'))->toBe(['inner']);
});
