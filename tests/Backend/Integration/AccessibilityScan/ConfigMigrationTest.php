<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('AccessibilityScan config migration to the shared browser runtime', function () {
    beforeEach(function () {
        $this->setup = Mage::getResourceModel('core/setup', 'accessibilityscan_setup');
        $this->connection = $this->setup->getConnection();
        $this->table = $this->setup->getTable('core/config_data');
        $this->script = Mage::getModuleDir('sql', 'Maho_AccessibilityScan') . '/accessibilityscan_setup/upgrade-1.0.0-1.1.0.php';
        foreach (['node_path', 'npm_path'] as $field) {
            $this->setup->deleteConfigData("accessibilityscan/advanced/$field");
            $this->setup->deleteConfigData("system/browser/$field");
        }
    });

    afterEach(function () {
        foreach (['node_path', 'npm_path'] as $field) {
            $this->setup->deleteConfigData("accessibilityscan/advanced/$field");
            $this->setup->deleteConfigData("system/browser/$field");
        }
    });

    it('moves a configured path to system/browser and drops the old row', function () {
        $this->setup->setConfigData('accessibilityscan/advanced/node_path', '/usr/local/bin/node');
        $this->setup->setConfigData('accessibilityscan/advanced/npm_path', '/usr/local/bin/npm');

        $script = $this->script;
        (fn() => include $script)->call($this->setup);

        $rows = $this->connection->fetchPairs(
            $this->connection->select()->from($this->table, ['path', 'value'])
                ->where('path LIKE ?', 'system/browser/%')
                ->orWhere('path LIKE ?', 'accessibilityscan/advanced/%')
                ->order('path'),
        );
        expect($rows)->toBe([
            'system/browser/node_path' => '/usr/local/bin/node',
            'system/browser/npm_path' => '/usr/local/bin/npm',
        ]);
    });

    it('keeps an existing shared value and runs again without changes', function () {
        $this->setup->setConfigData('system/browser/node_path', '/opt/node/bin/node');
        $this->setup->setConfigData('accessibilityscan/advanced/node_path', '/usr/local/bin/node');

        $script = $this->script;
        (fn() => include $script)->call($this->setup);
        (fn() => include $script)->call($this->setup);

        $rows = $this->connection->fetchPairs(
            $this->connection->select()->from($this->table, ['path', 'value'])
                ->where('path LIKE ?', 'system/browser/%')
                ->orWhere('path LIKE ?', 'accessibilityscan/advanced/%')
                ->order('path'),
        );
        expect($rows)->toBe(['system/browser/node_path' => '/opt/node/bin/node']);
    });
});
