<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Schema\Applier;
use Maho\Db\Schema\Status;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');
    $this->table = Mage::getSingleton('core/resource')->getTableName('core/resource');

    $this->stored = fn(): mixed => $this->adapter->fetchOne(
        $this->adapter->select()->from($this->table, ['version'])->where('code = ?', Status::RESOURCE_CODE),
    );
    $this->store = fn(string $value) => $this->adapter->update(
        $this->table,
        ['version' => $value],
        ['code = ?' => Status::RESOURCE_CODE],
    );

    // The app memoizes its verdict for the whole process, so a test that wants
    // a fresh one has to clear it.
    $this->forgetVerdict = function (): void {
        (new ReflectionProperty(Mage_Core_Model_App::class, '_schemaUpdatePending'))
            ->setValue(Mage::app(), null);
    };
});

it('records the applied schema fingerprint when installing', function () {
    expect(($this->stored)())->toBe(Status::fingerprint());
});

it('reports no pending update on a converged database', function () {
    expect(Status::isConverged($this->adapter))->toBeTrue();
    expect(Mage::app()->isSchemaUpdatePending())->toBeFalse();
});

it('re-records the fingerprint when a schema source changes but the database does not', function () {
    ($this->store)('stale-fingerprint');

    expect(Status::isConverged($this->adapter))->toBeTrue();
    expect(($this->stored)())->toBe(Status::fingerprint());
});

it('reports a pending update when the database diverges from the declared schema', function () {
    $flagTable = Mage::getSingleton('core/resource')->getTableName('core/flag');
    $connection = $this->adapter->getConnection();

    // An index no module declares: convergence drops it, so the planner has
    // work to do. Cheaper to undo than a missing table, and carries no data.
    $connection->executeStatement("CREATE INDEX idx_maho_status_test ON $flagTable (state)");
    ($this->store)('stale-fingerprint');

    try {
        expect(Status::isConverged($this->adapter))->toBeFalse();
        expect(($this->stored)())->toBe('stale-fingerprint');
    } finally {
        Applier::applyAll($this->adapter);
    }

    expect(Status::isConverged($this->adapter))->toBeTrue();
    expect(($this->stored)())->toBe(Status::fingerprint());
});

it('drops a cached pending verdict once the fingerprint is recorded as applied', function () {
    // What a request that raced ./maho migrate leaves behind: the fingerprint
    // is unchanged by the migration, so a blindly trusted cache entry would
    // refuse every request for good.
    $fingerprint = Status::fingerprint();
    Mage::app()->saveCache(
        "pending:$fingerprint",
        Mage_Core_Model_App::CACHE_ID_SCHEMA_STATE,
        [Mage_Core_Model_Config::CACHE_TAG],
    );
    ($this->forgetVerdict)();

    try {
        expect(Mage::app()->isSchemaUpdatePending())->toBeFalse();
        expect(Mage::app()->loadCache(Mage_Core_Model_App::CACHE_ID_SCHEMA_STATE))->toBe("ok:$fingerprint");
    } finally {
        Mage::app()->removeCache(Mage_Core_Model_App::CACHE_ID_SCHEMA_STATE);
        ($this->forgetVerdict)();
    }
});

it('does not report a pending update over the storage engine of an undeclared table', function () {
    if (!($this->adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: other backends have no storage-engine concept.');
    }

    // Applier::plan scans the whole database for legacy engines, so a stray
    // third-party MyISAM table would otherwise report the declared schema as
    // behind and refuse every web request.
    $table = 'test_engine_' . uniqid();
    $this->adapter->query(sprintf(
        'CREATE TABLE %s (id INT NOT NULL) ENGINE=MyISAM',
        $this->adapter->quoteIdentifier($table),
    ));
    ($this->store)('stale-fingerprint');

    try {
        expect(Status::isConverged($this->adapter))->toBeTrue();
        expect(($this->stored)())->toBe(Status::fingerprint());
    } finally {
        $this->adapter->dropTable($table);
    }
});
