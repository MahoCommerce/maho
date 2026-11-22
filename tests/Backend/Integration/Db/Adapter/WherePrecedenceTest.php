<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Expr;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->table = 'test_where_precedence_' . uniqid();
    $this->adapter->query(sprintf(
        'CREATE TABLE %s (option_id INT NOT NULL, store_id INT NOT NULL, title VARCHAR(64) NOT NULL)',
        $this->adapter->quoteIdentifier($this->table),
    ));
    $this->rows = [];
    foreach ([1, 2] as $optionId) {
        foreach ([0, 2, 3] as $storeId) {
            $this->rows[] = ['option_id' => $optionId, 'store_id' => $storeId, 'title' => "Option $optionId store $storeId"];
        }
    }
    $this->adapter->insertMultiple($this->table, $this->rows);
});

afterEach(function () {
    $this->adapter->dropTable($this->table);
});

it('keeps OR terms within the selected option when writing rows', function (string $form, int $storeId, string $operation) {
    $storeCondition = "store_id = $storeId OR store_id = 0";
    $where = match ($form) {
        'bound' => ['option_id = ?' => 1, 'store_id = ? OR store_id = 0' => $storeId],
        'raw' => ['option_id = 1', $storeCondition],
        'expression' => [new Expr('option_id = 1'), new Expr($storeCondition)],
        'grouped' => ['option_id = ?' => 1, '(store_id = ? OR store_id = 0)' => $storeId],
    };
    $expected = [];
    foreach ($this->rows as $row) {
        $matches = $row['option_id'] === 1 && in_array($row['store_id'], [0, $storeId], true);
        if ($matches && $operation === 'delete') {
            continue;
        }
        if ($matches) {
            $row['title'] = 'Updated title';
        }
        $expected[] = $row['title'];
    }

    $affected = $operation === 'delete'
        ? $this->adapter->delete($this->table, $where)
        : $this->adapter->update($this->table, ['title' => 'Updated title'], $where);

    $titles = $this->adapter->fetchCol($this->adapter->select()
        ->from($this->table, ['title'])
        ->order(['option_id', 'store_id']));

    expect($affected)->toBe($storeId === 0 ? 1 : 2)
        ->and($titles)->toBe($expected);
})->with(['bound', 'raw', 'expression', 'grouped'])
    ->with([0, 2])
    ->with(['delete', 'update']);

it('preserves string and empty WHERE behavior', function (string $where, string $operation) {
    $expected = [];
    $affectedCount = 0;
    foreach ($this->rows as $row) {
        $matches = $where === ''
            || ($row['option_id'] === 1 && $row['store_id'] === 2)
            || ($row['option_id'] === 2 && $row['store_id'] === 3);
        if ($matches) {
            ++$affectedCount;
            if ($operation === 'delete') {
                continue;
            }
            $row['title'] = 'Updated title';
        }
        $expected[] = $row['title'];
    }

    $affected = $operation === 'delete'
        ? $this->adapter->delete($this->table, $where)
        : $this->adapter->update($this->table, ['title' => 'Updated title'], $where);

    $titles = $this->adapter->fetchCol($this->adapter->select()
        ->from($this->table, ['title'])
        ->order(['option_id', 'store_id']));

    expect($affected)->toBe($affectedCount)
        ->and($titles)->toBe($expected);
})->with(['', 'option_id = 1 AND store_id = 2 OR option_id = 2 AND store_id = 3'])
    ->with(['delete', 'update']);
