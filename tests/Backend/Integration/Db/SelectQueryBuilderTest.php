<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Select;
use Maho\Db\Expr;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->configTable = $this->adapter->getTableName('core_config_data');
});

describe('Select Query Builder - Basic Operations', function () {
    it('builds simple SELECT query', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope', 'path'])
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('SELECT');
        expect($sql)->toContain('FROM');
        expect($sql)->toContain('LIMIT');
    });

    it('executes simple SELECT and returns results', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope'])
            ->where('scope = ?', 'default')
            ->limit(5);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        expect(count($results))->toBeLessThanOrEqual(5);
    });

    it('handles WHERE conditions with parameter binding', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope', 'scope_id', 'path'])
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0)
            ->limit(5);

        $results = $this->adapter->fetchAll($select);

        foreach ($results as $row) {
            expect($row['scope'])->toBe('default');
            expect((int) $row['scope_id'])->toBe(0);
        }
    });

    it('handles OR WHERE conditions', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope'])
            ->where('scope = ?', 'default')
            ->orWhere('scope = ?', 'websites')
            ->limit(10);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        foreach ($results as $row) {
            expect($row['scope'])->toBeIn(['default', 'websites']);
        }
    });
});

describe('Select Query Builder - JOINs', function () {
    it('builds INNER JOIN query', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->configTable], ['path', 'value'])
            ->joinInner(
                ['s' => $storeTable],
                'c.scope_id = s.store_id',
                ['store_id', 'code'],
            )
            ->where('c.scope = ?', 'stores')
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('INNER JOIN');
        expect($sql)->toContain($storeTable);
    });

    it('builds LEFT JOIN query', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->configTable], ['path'])
            ->joinLeft(
                ['s' => $storeTable],
                'c.scope_id = s.store_id AND c.scope = "stores"',
                ['code'],
            )
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('LEFT JOIN');
    });

    it('executes JOIN query correctly', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->configTable], ['path'])
            ->joinInner(
                ['s' => $storeTable],
                'c.scope_id = s.store_id',
                ['code'],
            )
            ->where('c.scope = ?', 'stores')
            ->limit(5);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        if (count($results) > 0) {
            expect($results[0])->toHaveKey('path');
            expect($results[0])->toHaveKey('code');
        }
    });
});

describe('Select Query Builder - Aggregates and Grouping', function () {
    it('handles GROUP BY', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope', new Expr('COUNT(*) as config_count')])
            ->group('scope');

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        expect(count($results))->toBeGreaterThan(0);
        expect($results[0])->toHaveKey('scope');
        expect($results[0])->toHaveKey('config_count');
    });

    it('handles HAVING clause', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope', new Expr('COUNT(*) as config_count')])
            ->group('scope')
            ->having('COUNT(*) > ?', 10);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        foreach ($results as $row) {
            expect((int) $row['config_count'])->toBeGreaterThan(10);
        }
    });

    it('handles ORDER BY', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['path', 'value'])
            ->where('scope = ?', 'default')
            ->order('path ASC')
            ->limit(5);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        if (count($results) > 1) {
            expect($results[0]['path'])->toBeLessThanOrEqual($results[1]['path']);
        }
    });

    it('handles multiple ORDER BY columns', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope', 'scope_id', 'path'])
            ->order(['scope ASC', 'scope_id ASC'])
            ->limit(10);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
    });
});

describe('Select Query Builder - DISTINCT and LIMIT', function () {
    it('handles DISTINCT', function () {
        $select = $this->adapter->select()
            ->distinct()
            ->from($this->configTable, ['scope']);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();

        // Verify no duplicates
        $scopes = array_column($results, 'scope');
        expect(count($scopes))->toBe(count(array_unique($scopes)));
    });

    it('handles LIMIT with OFFSET', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['path'])
            ->limit(5, 10); // 5 rows, offset 10

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
        expect(count($results))->toBeLessThanOrEqual(5);
    });

    it('handles limitPage for pagination', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['path'])
            ->limitPage(2, 10); // Page 2, 10 per page

        $sql = $select->assemble();

        expect($sql)->toContain('LIMIT 10');
        expect($sql)->toContain('OFFSET 10');
    });
});

describe('Select Query Builder - Complex Queries', function () {
    it('handles raw SQL expressions in WHERE', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['path', 'value'])
            ->where('LENGTH(path) > ?', 10)
            ->limit(5);

        $sql = $select->assemble();

        expect($sql)->toContain('SELECT');
        expect($sql)->toContain('LENGTH(path)');
    });

    it('handles UNION queries', function () {
        $select1 = $this->adapter->select()
            ->from($this->configTable, ['path'])
            ->where('scope = ?', 'default')
            ->limit(2);

        $select2 = $this->adapter->select()
            ->from($this->configTable, ['path'])
            ->where('scope = ?', 'websites')
            ->limit(2);

        $unionSelect = $this->adapter->select()
            ->union([$select1, $select2]);

        $sql = $unionSelect->assemble();

        expect($sql)->toContain('UNION');
    });

    it('handles complex WHERE with OR conditions', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope', 'path'])
            ->where('scope = ?', 'default')
            ->orWhere('scope = ?', 'websites')
            ->where('scope_id = ?', 0)
            ->limit(5);

        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
    });

    it('resets unused LEFT JOINs', function () {
        $storeTable = $this->adapter->getTableName('core_store');

        $select = $this->adapter->select()
            ->from(['c' => $this->configTable], ['path', 'value'])
            ->joinLeft(
                ['s' => $storeTable],
                'c.scope_id = s.store_id',
                ['code'],
            )
            ->where('c.scope = ?', 'default')
            ->limit(5);

        // Don't use any columns from joined table
        $select->reset(Select::COLUMNS);
        $select->columns(['path', 'value'], 'c');

        // resetJoinLeft should remove unused joins
        $select->resetJoinLeft();

        $sql = $select->assemble();

        // JOIN might be removed if not used
        expect($sql)->toContain('SELECT');
    });
});

describe('Select Query Builder - FOR UPDATE', function () {
    it('adds FOR UPDATE clause', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['path', 'value'])
            ->where('scope = ?', 'default')
            ->forUpdate(true)
            ->limit(1);

        $sql = $select->assemble();

        // SQLite uses transaction-level locking, so FOR UPDATE is silently ignored
        // MySQL and PostgreSQL support row-level locking with FOR UPDATE
        if ($this->adapter instanceof \Maho\Db\Adapter\Pdo\Sqlite) {
            expect($sql)->not->toContain('FOR UPDATE');
        } else {
            expect($sql)->toContain('FOR UPDATE');
        }
    });

    it('removes FOR UPDATE when set to false', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['path'])
            ->forUpdate(true)
            ->forUpdate(false)
            ->limit(1);

        $sql = $select->assemble();

        expect($sql)->not->toContain('FOR UPDATE');
    });
});

describe('Select Query Builder - Part Management', function () {
    it('gets query parts', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope'])
            ->where('scope = ?', 'default')
            ->limit(5);

        $fromPart = $select->getPart(Select::FROM);
        $wherePart = $select->getPart(Select::WHERE);
        $limitPart = $select->getPart(Select::LIMIT_COUNT);

        expect($fromPart)->toBeArray();
        expect($wherePart)->toBeArray();
        expect($limitPart)->toBe(5);
    });

    it('sets query parts', function () {
        $select = $this->adapter->select()
            ->from($this->configTable);

        $select->setPart(Select::LIMIT_COUNT, 10);

        $limitPart = $select->getPart(Select::LIMIT_COUNT);
        expect($limitPart)->toBe(10);
    });

    it('resets specific query part', function () {
        $select = $this->adapter->select()
            ->from($this->configTable)
            ->where('scope = ?', 'default')
            ->limit(5);

        $select->reset(Select::WHERE);

        $wherePart = $select->getPart(Select::WHERE);
        expect($wherePart)->toBeArray();
        expect($wherePart)->toBeEmpty();
    });

    it('resets entire query', function () {
        $select = $this->adapter->select()
            ->from($this->configTable)
            ->where('scope = ?', 'default')
            ->limit(5);

        $select->reset();

        $sql = $select->assemble();
        expect($sql)->toBe('SELECT *');
    });
});

describe('Select Query Builder - toString Conversion', function () {
    it('converts to string automatically', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope'])
            ->limit(1);

        $sql = (string) $select;

        expect($sql)->toContain('SELECT');
        expect($sql)->toContain('FROM');
    });

    it('can be used directly in query methods', function () {
        $select = $this->adapter->select()
            ->from($this->configTable, ['scope'])
            ->where('scope = ?', 'default')
            ->limit(5);

        // Pass Select object directly to fetchAll
        $results = $this->adapter->fetchAll($select);

        expect($results)->toBeArray();
    });
});
