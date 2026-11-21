<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('writes local.xml without granting access to other users', function (): void {
    $dir = sys_get_temp_dir() . '/maho_install_' . uniqid();
    mkdir($dir, 0700, true);
    $path = $dir . '/local.xml';
    $installer = new Mage_Install_Model_Installer();
    $installer->setDataModel(new Mage_Install_Model_Installer_Data());
    $config = new class ($path, $installer) extends Mage_Install_Model_Installer_Config {
        public function __construct(string $path, Mage_Install_Model_Installer $installer)
        {
            parent::__construct();
            $this->_localConfigFile = $path;
            $this->_installer = $installer;
        }
    };

    try {
        $config->setConfigData([
            'root_dir' => Mage::getBaseDir(),
            'unsecure_base_url' => 'http://localhost/',
            'secure_base_url' => 'https://localhost/',
            'key_date' => 'now',
            'db_prefix' => '',
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_pass' => 'password',
            'db_name' => 'database',
            'db_init_statemants' => '',
            'db_engine' => 'sqlite',
            'session_save' => 'files',
            'admin_frontname' => 'admin',
        ]);

        $config->install();

        $mode = fileperms($path) & 0777;
        expect($mode & 0007)->toBe(0)
            ->and($mode & 0060)->toBe(0060);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        rmdir($dir);
    }
});
