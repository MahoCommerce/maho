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

it('defaults to the file backend and contends across instances', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('core_lock_test'))->toBeTrue();
    expect($manager->isHeld('core_lock_test'))->toBeTrue();

    $contender = new \Maho\Lock\FileLock(
        Mage::getConfig()->getVarDir('locks') . DS . 'core_lock_test.lock',
    );
    expect($contender->acquire())->toBeFalse();

    expect($manager->release('core_lock_test'))->toBeTrue();
    expect($manager->isHeld('core_lock_test'))->toBeFalse();
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
