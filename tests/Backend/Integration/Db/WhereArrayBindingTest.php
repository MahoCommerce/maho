<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An array value is one value list for one placeholder. Against several placeholders the
 * whole list went into each of them, and a nightly AI usage cron failed on the broken SQL.
 */

beforeEach(function () {
    $this->resource = Mage::getSingleton('core/resource');
    $this->adapter  = $this->resource->getConnection('core_read');
    $this->table    = $this->resource->getTableName('core/config_data');
});

it('expands an array value into a single IN placeholder', function () {
    $scopes = ['default', 'stores'];
    $select = $this->adapter->select()
        ->from($this->table, ['path'])
        ->where('scope IN (?)', $scopes);

    expect($select->assemble())->toContain('IN (' . $this->adapter->quote($scopes) . ')');
});

// In a variable so that SplitBetweenBindRector does not rewrite the calls under test.
$range = 'config_id BETWEEN ? AND ?';

it('rejects an array value against a WHERE condition with two placeholders', function () use ($range) {
    expect(fn() => $this->adapter->select()
        ->from($this->table, ['path'])
        ->where($range, [1, 5]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an array value against a HAVING condition with two placeholders', function () use ($range) {
    expect(fn() => $this->adapter->select()
        ->from($this->table, ['scope'])
        ->group('scope')
        ->having($range, [1, 5]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an array value against a DELETE condition with two placeholders', function () use ($range) {
    expect(fn() => $this->adapter->delete($this->table, [$range => [1, 5]]))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts one scalar value for each placeholder of a range condition', function () {
    $select = $this->adapter->select()
        ->from($this->table, ['config_id'])
        ->where('config_id >= ?', 1)
        ->where('config_id <= ?', 5);

    expect($this->adapter->fetchCol($select))->toBeArray();
});

it('keeps the same scalar in every placeholder', function () {
    $condition = $this->adapter->quoteInto('a = ? OR b = ?', 7);

    expect($condition)->toBe('a = ' . $this->adapter->quote(7) . ' OR b = ' . $this->adapter->quote(7));
});

it('fills several list placeholders when the caller states the count', function () {
    $ids  = [1, 2];
    $list = $this->adapter->quote($ids);

    expect($this->adapter->quoteInto('a IN (?) OR b IN (?)', $ids, null, 2))
        ->toBe("a IN ($list) OR b IN ($list)");
});

it('quotes an empty list as NULL on every engine', function () {
    expect($this->adapter->quote([]))->toBe('NULL');

    $select = $this->adapter->select()
        ->from($this->table, ['config_id'])
        ->where('config_id IN (?)', []);

    expect($this->adapter->fetchCol($select))->toBe([]);
});

it('reads catalog index attribute data with one list in two placeholders', function () {
    // Ids that match nothing still prove the statement parses on every engine.
    $data = Mage::getResourceModel('catalogindex/data_abstract');

    expect($data->getAttributeData([1, 2], [1, 2], 1))->toBeArray();
    expect($data->getAttributeData([1, 2], [], 1))->toBe([]);
});
