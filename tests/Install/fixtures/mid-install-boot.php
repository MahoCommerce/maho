<?php

/**
 * Boots Maho the way the wizard leaves it between the Configuration step and the last step:
 * app/etc/local.xml is loaded, but the store does not count as installed yet.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/install/wizard/license';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';

Mage::setRoot($root);
Mage::run('', 'store', ['is_installed' => false, 'var_dir' => $argv[1]]);

$setupApplied = new ReflectionProperty(Mage_Core_Model_Resource_Setup::class, '_schemaUpdatesChecked');
printf(
    "\nSETUP_SCRIPTS_APPLIED=%d STORES_LOADED=%d\n",
    (int) $setupApplied->getValue(),
    count(Mage::app()->getStores(true)),
);
