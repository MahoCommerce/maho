<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * PostgreSQL and SQLite name one ON CONFLICT target, so a table with two unique indexes
 * needs the one that identifies the row. The catalog URL indexer got the other one.
 */

const UPSERT_TEST_ID_PATH = 'category/99000001';

beforeEach(function () {
    $this->resource = Mage::getSingleton('core/resource');
    $this->adapter  = $this->resource->getConnection('core_write');
    $this->table    = $this->resource->getTableName('core/url_rewrite');
});

afterEach(function () {
    $this->adapter->delete($this->table, ['id_path = ?' => UPSERT_TEST_ID_PATH]);
});

it('updates the row that carries the same identity key', function () {
    $row = [
        'store_id'     => 1,
        'category_id'  => null,
        'product_id'   => null,
        'id_path'      => UPSERT_TEST_ID_PATH,
        'request_path' => 'upsert-test-first',
        'target_path'  => 'catalog/category/view/id/99000001',
        'is_system'    => 1,
    ];
    $this->adapter->insert($this->table, $row);

    $row['request_path'] = 'upsert-test-second';
    Mage::getResourceSingleton('catalog/url')->saveRewrite($row, null);

    $stored = $this->adapter->fetchAll(
        $this->adapter->select()
            ->from($this->table, ['request_path'])
            ->where('id_path = ?', UPSERT_TEST_ID_PATH),
    );

    expect($stored)->toHaveCount(1);
    expect($stored[0]['request_path'])->toBe('upsert-test-second');
});

it('keeps a unique index out of the conflict target when the caller updates it', function () {
    $row = [
        'store_id'     => 1,
        'category_id'  => null,
        'product_id'   => null,
        'id_path'      => UPSERT_TEST_ID_PATH,
        'request_path' => 'upsert-test-first',
        'target_path'  => 'catalog/category/view/id/99000001',
        'is_system'    => 1,
    ];
    $this->adapter->insert($this->table, $row);

    $row['request_path'] = 'upsert-test-third';
    $this->adapter->insertOnDuplicate($this->table, $row, ['request_path', 'target_path']);

    expect($this->adapter->fetchOne(
        $this->adapter->select()
            ->from($this->table, ['request_path'])
            ->where('id_path = ?', UPSERT_TEST_ID_PATH),
    ))->toBe('upsert-test-third');
});
