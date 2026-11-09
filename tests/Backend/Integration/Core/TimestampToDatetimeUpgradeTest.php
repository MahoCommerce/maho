<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

describe('Core upgrade 1.6.0.10 to 2.0.0, TIMESTAMP to DATETIME', function () {
    beforeEach(function () {
        $this->setup = Mage::getResourceModel('core/setup', 'core_setup');
        $this->connection = $this->setup->getConnection();
        if (!($this->connection instanceof Mysql)) {
            $this->markTestSkipped('MySQL-only test: the conversion runs on MySQL and MariaDB only.');
        }
        $this->script = Mage::getModuleDir('sql', 'Mage_Core') . '/core_setup/upgrade-1.6.0.10-2.0.0.php';
        $this->connection->query('DROP VIEW IF EXISTS ts_upgrade_view');
        $this->connection->query('DROP TABLE IF EXISTS ts_upgrade_base');
    });

    afterEach(function () {
        if (isset($this->connection) && $this->connection instanceof Mysql) {
            $this->connection->query('DROP VIEW IF EXISTS ts_upgrade_view');
            $this->connection->query('DROP TABLE IF EXISTS ts_upgrade_base');
        }
    });

    it('converts a table column and skips a view over it', function () {
        $this->connection->query('CREATE TABLE ts_upgrade_base (id INT NOT NULL PRIMARY KEY, ts TIMESTAMP NULL DEFAULT NULL)');
        $this->connection->query('CREATE VIEW ts_upgrade_view AS SELECT id, ts FROM ts_upgrade_base');

        $script = $this->script;
        expect(fn() => (fn() => include $script)->call($this->setup))->not->toThrow(Throwable::class);

        $type = $this->connection->fetchOne(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ts_upgrade_base' AND COLUMN_NAME = 'ts'",
        );
        expect(strtolower((string) $type))->toBe('datetime');
    });
});
