<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * renameTablesBatch() backs swapping a table for a rebuilt copy, so it has to be
 * all-or-nothing on every backend.
 */
beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->prefix = 'test_rename_' . uniqid() . '_';

    foreach (['a', 'b'] as $suffix) {
        $table = $this->prefix . $suffix;
        $this->adapter->query(sprintf('CREATE TABLE %s (id INT NOT NULL)', $this->adapter->quoteIdentifier($table)));
        $this->adapter->insert($table, ['id' => $suffix === 'a' ? 1 : 2]);
    }
});

afterEach(function () {
    foreach (['a', 'b', 'tmp'] as $suffix) {
        if ($this->adapter->isTableExists($this->prefix . $suffix)) {
            $this->adapter->dropTable($this->prefix . $suffix);
        }
    }
});

it('swaps two tables in one batch', function () {
    $a = $this->prefix . 'a';
    $b = $this->prefix . 'b';
    $tmp = $this->prefix . 'tmp';

    $this->adapter->renameTablesBatch([
        ['oldName' => $a, 'newName' => $tmp],
        ['oldName' => $b, 'newName' => $a],
        ['oldName' => $tmp, 'newName' => $b],
    ]);

    expect((int) $this->adapter->fetchOne(sprintf('SELECT id FROM %s', $this->adapter->quoteIdentifier($a))))->toBe(2);
    expect((int) $this->adapter->fetchOne(sprintf('SELECT id FROM %s', $this->adapter->quoteIdentifier($b))))->toBe(1);
    expect($this->adapter->isTableExists($tmp))->toBeFalse();
});

it('leaves every name unchanged when one rename in the batch fails', function () {
    $a = $this->prefix . 'a';
    $b = $this->prefix . 'b';
    $tmp = $this->prefix . 'tmp';

    // The second rename names no existing table, so the first must not survive:
    // a half-applied swap would leave the live table name missing.
    expect(fn() => $this->adapter->renameTablesBatch([
        ['oldName' => $a, 'newName' => $tmp],
        ['oldName' => $this->prefix . 'absent', 'newName' => $b . '_new'],
    ]))->toThrow(Exception::class);

    expect($this->adapter->isTableExists($a))->toBeTrue();
    expect($this->adapter->isTableExists($tmp))->toBeFalse();
});
