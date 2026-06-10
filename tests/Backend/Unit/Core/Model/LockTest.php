<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

it('is re-entrant within the process', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_test'))->toBeTrue();
    expect($manager->acquire('core_lock_test'))->toBeTrue();
    expect(Mage::getModel('core/lock')->acquire('core_lock_test'))->toBeTrue();
    $manager->release('core_lock_test');
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

it('is re-entrant with a single release on the db backend too', function () {
    Mage::getConfig()->setNode(Mage_Core_Model_Lock::XML_PATH_BACKEND, 'db');

    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_db_reentrant'))->toBeTrue();
    expect($manager->acquire('core_lock_db_reentrant'))->toBeTrue();
    expect(Mage::getModel('core/lock')->acquire('core_lock_db_reentrant'))->toBeTrue();
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
