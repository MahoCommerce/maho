<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests the cron:run concurrency guard (issue #993): one cron.{mode} lock
 * per mode through the core/lock service, rejected while held, released
 * on process exit. Contention is simulated with a raw flock on the lock
 * file, which contends at kernel level like a separate process would.
 */

/** @return resource */
function cronLockContender(string $mode)
{
    $handle = fopen(Mage::getConfig()->getVarDir('locks') . DS . "cron.{$mode}.lock", 'c');
    expect($handle)->not->toBeFalse();
    return $handle;
}

it('rejects a second holder of the cron lock while held', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('cron.default'))->toBeTrue();

    $contender = cronLockContender('default');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeFalse();

    $manager->release('cron.default');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeTrue();
    flock($contender, LOCK_UN);
    fclose($contender);
});

it('keeps the lock held even if the acquiring model instance is destroyed', function () {
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('cron.always'))->toBeTrue();
    unset($manager);

    $contender = cronLockContender('always');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeFalse();

    Mage::getModel('core/lock')->release('cron.always');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeTrue();
    flock($contender, LOCK_UN);
    fclose($contender);
});

it('uses independent locks per mode', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');

    expect($manager->acquire('cron.default'))->toBeTrue();
    expect($manager->acquire('cron.always'))->toBeTrue();

    $manager->release('cron.default');
    $manager->release('cron.always');
});
