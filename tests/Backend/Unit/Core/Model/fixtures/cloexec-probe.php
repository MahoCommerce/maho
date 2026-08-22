<?php

/**
 * Holds a file-backend lock while spawning a detached child, then prints the child
 * pid: without O_CLOEXEC the child would inherit the lock descriptor and keep the
 * lock held after this process exits.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

$root = dirname(__DIR__, 6);
require $root . '/vendor/autoload.php';

Mage::setRoot($root);
Mage::app();

// Pin the file backend: the installed local.xml may configure db, which creates
// no lock file and would let the test pass without exercising O_CLOEXEC
Mage::getConfig()->setNode(Mage_Core_Model_Lock::XML_PATH_BACKEND, 'file');

if (!Mage::getModel('core/lock')->acquire('core_lock_cloexec')) {
    fwrite(STDERR, 'unable to acquire core_lock_cloexec');
    exit(1);
}

exec('nohup sleep 30 > /dev/null 2>&1 & echo $!', $out);
$child = (int) end($out);
if ($child <= 0) {
    fwrite(STDERR, 'unable to spawn the detached child');
    exit(1);
}
echo "\n{$child}\n";
