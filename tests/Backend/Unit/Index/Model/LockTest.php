<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('handles file locks through the facade', function () {
    $lock = Mage_Index_Model_Lock::getInstance();

    expect($lock->setLock('index_lock_test', true))->toBeTrue();
    expect($lock->isLockExists('index_lock_test', true))->toBeTrue();

    $contender = new \Maho\Lock\FileLock(
        Mage::getConfig()->getVarDir('locks') . DS . 'index_lock_test.lock',
    );
    expect($contender->acquire())->toBeFalse();

    expect($lock->releaseLock('index_lock_test', true))->toBeTrue();
    expect($lock->isLockExists('index_lock_test', true))->toBeFalse();
});

it('handles db locks through the facade', function () {
    $lock = Mage_Index_Model_Lock::getInstance();

    expect($lock->setLock('index_lock_db_test'))->toBeTrue();
    expect($lock->isLockExists('index_lock_db_test'))->toBeTrue();
    expect($lock->releaseLock('index_lock_db_test'))->toBeTrue();
    expect($lock->isLockExists('index_lock_db_test'))->toBeFalse();
});
