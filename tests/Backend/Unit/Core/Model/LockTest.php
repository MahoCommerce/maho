<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

afterEach(function () {
    Mage::getConfig()->setNode(Mage_Core_Model_Lock::XML_PATH_BACKEND, 'file');
});

it('defaults to the file backend and contends at kernel level', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_test'))->toBeTrue();
    expect($manager->isHeld('core_lock_test'))->toBeTrue();

    // Raw flock contends like a separate process would
    $contender = fopen(Mage::getConfig()->getVarDir('locks') . DS . 'core_lock_test.lock', 'c');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeFalse();

    expect($manager->release('core_lock_test'))->toBeTrue();
    expect($manager->isHeld('core_lock_test'))->toBeFalse();
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeTrue();
    flock($contender, LOCK_UN);
    fclose($contender);
});

it('rejects re-acquiring a lock the process already holds', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_test'))->toBeTrue();
    // Already held by this process: a second acquire fails, even from a fresh instance
    expect($manager->acquire('core_lock_test'))->toBeFalse();
    expect(Mage::getModel('core/lock')->acquire('core_lock_test'))->toBeFalse();
    // A single release frees it
    expect($manager->isHeld('core_lock_test'))->toBeTrue();
    $manager->release('core_lock_test');
    expect($manager->isHeld('core_lock_test'))->toBeFalse();
});

it('sanitizes lock names containing external input', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('paypal_order_../../evil'))->toBeTrue();

    $lockDir = Mage::getConfig()->getVarDir('locks');
    expect(file_exists($lockDir . DS . 'paypal_order_.._.._evil.lock'))->toBeTrue();
    expect(file_exists(dirname($lockDir) . DS . 'evil.lock'))->toBeFalse();

    $manager->release('paypal_order_../../evil');
});

it('uses db advisory locks when configured in local.xml', function () {
    Mage::getConfig()->setNode(Mage_Core_Model_Lock::XML_PATH_BACKEND, 'db');

    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_db_test'))->toBeTrue();
    expect($manager->isHeld('core_lock_db_test'))->toBeTrue();
    expect($manager->release('core_lock_db_test'))->toBeTrue();
    expect($manager->isHeld('core_lock_db_test'))->toBeFalse();

    // No lock file must be created by the db backend
    expect(file_exists(Mage::getConfig()->getVarDir('locks') . DS . 'core_lock_db_test.lock'))->toBeFalse();
});

it('rejects re-acquiring a held lock on the db backend too', function () {
    Mage::getConfig()->setNode(Mage_Core_Model_Lock::XML_PATH_BACKEND, 'db');

    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_db_reentrant'))->toBeTrue();
    expect($manager->acquire('core_lock_db_reentrant'))->toBeFalse();
    expect(Mage::getModel('core/lock')->acquire('core_lock_db_reentrant'))->toBeFalse();
    expect($manager->release('core_lock_db_reentrant'))->toBeTrue();
    expect($manager->isHeld('core_lock_db_reentrant'))->toBeFalse();
});

it('handles db lock names exceeding the MySQL 64-character limit', function () {
    Mage::getConfig()->setNode(Mage_Core_Model_Lock::XML_PATH_BACKEND, 'db');

    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    $name = 'paypal_order_' . str_repeat('X', 80);
    expect($manager->acquire($name))->toBeTrue();
    expect($manager->isHeld($name))->toBeTrue();
    expect($manager->release($name))->toBeTrue();
    expect($manager->isHeld($name))->toBeFalse();
});

it('cleans up only stale, unheld lock files', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    $lockDir = Mage::getConfig()->getVarDir('locks');

    $old = $lockDir . DS . 'paypal_order_stale.lock';
    $recent = $lockDir . DS . 'paypal_order_recent.lock';
    touch($old, time() - 100000);   // older than the 86400s default cutoff
    touch($recent, time() - 100);   // well within the cutoff

    // An old file that is currently held must survive
    expect($manager->acquire('cron.default'))->toBeTrue();
    touch($lockDir . DS . 'cron.default.lock', time() - 100000);

    $removed = $manager->cleanupStaleLockFiles();

    expect(file_exists($old))->toBeFalse();                       // stale + unheld -> gone
    expect($removed)->toBeGreaterThanOrEqual(1);
    expect(file_exists($recent))->toBeTrue();                     // too recent -> kept
    expect(file_exists($lockDir . DS . 'cron.default.lock'))->toBeTrue(); // held -> kept

    $manager->release('cron.default');
    @unlink($recent);
});

// Issue #1314: the holder runs in its own process so it can exit while its child lives
it('does not leak a held lock into a process spawned while holding it', function () {
    $root = Mage::getBaseDir();
    $lockDir = Mage::getConfig()->getVarDir('locks');
    $probe = Mage::getBaseDir('var') . DS . 'cloexec-probe.php';

    file_put_contents($probe, <<<PHP
        <?php
        require '{$root}/vendor/autoload.php';
        Mage::setRoot('{$root}');
        Mage::app();
        Mage::getModel('core/lock')->acquire('core_lock_cloexec');
        exec('nohup sleep 30 > /dev/null 2>&1 & echo \$!', \$out);
        echo end(\$out);
        PHP);

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>/dev/null', $output);
    $child = (int) end($output);
    @unlink($probe);

    // A dead child would free the descriptor and pass the test for the wrong reason
    exec('ps -p ' . $child, $alive);
    expect(count($alive))->toBeGreaterThan(1);

    $contender = fopen($lockDir . DS . 'core_lock_cloexec.lock', 'c');
    $free = flock($contender, LOCK_EX | LOCK_NB);
    if ($free) {
        flock($contender, LOCK_UN);
    }
    fclose($contender);
    exec('kill ' . $child . ' 2>/dev/null');

    expect($free)->toBeTrue();
});
