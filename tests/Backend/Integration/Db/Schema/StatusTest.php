<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

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
