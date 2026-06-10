<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\Lock\FileLock;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->lockFile = Mage::getConfig()->getVarDir('locks') . DS . 'filelock.test.lock';
});

it('acquires and reports the lock', function () {
    $lock = new FileLock($this->lockFile);
    expect($lock->isAcquired())->toBeFalse();
    expect($lock->acquire())->toBeTrue();
    expect($lock->isAcquired())->toBeTrue();
    $lock->release();
});

it('is idempotent when acquiring twice on the same instance', function () {
    $lock = new FileLock($this->lockFile);
    expect($lock->acquire())->toBeTrue();
    expect($lock->acquire())->toBeTrue();
    $lock->release();
});

it('rejects a contender while the lock is held', function () {
    $holder = new FileLock($this->lockFile);
    $contender = new FileLock($this->lockFile);

    expect($holder->acquire())->toBeTrue();
    expect($contender->acquire())->toBeFalse();
    expect($contender->isAcquired())->toBeFalse();

    $holder->release();
});

it('frees the lock on release', function () {
    $holder = new FileLock($this->lockFile);
    $contender = new FileLock($this->lockFile);

    expect($holder->acquire())->toBeTrue();
    $holder->release();
    expect($holder->isAcquired())->toBeFalse();
    expect($contender->acquire())->toBeTrue();

    $contender->release();
});

it('frees the lock when the holder is destroyed', function () {
    $holder = new FileLock($this->lockFile);
    expect($holder->acquire())->toBeTrue();
    unset($holder);

    $contender = new FileLock($this->lockFile);
    expect($contender->acquire())->toBeTrue();
    $contender->release();
});

it('throws when the lock file cannot be created', function () {
    $lock = new FileLock('/nonexistent-dir/filelock.test.lock');
    $lock->acquire();
})->throws(RuntimeException::class, 'Unable to create lock file');
