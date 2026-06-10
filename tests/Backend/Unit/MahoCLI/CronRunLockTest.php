<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests the cron:run concurrency guard (issue #993): one cron.{mode} lock
 * per mode through the core/lock service, rejected while held, released
 * on process exit (simulated here at kernel level via a FileLock contender,
 * which contends like a separate process would).
 */

function cronContender(string $mode): \Maho\Lock\FileLock
{
    return new \Maho\Lock\FileLock(
        Mage::getConfig()->getVarDir('locks') . DS . "cron.{$mode}.lock",
    );
}

it('rejects a second holder of the cron lock while held', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('cron.default'))->toBeTrue();

    $contender = cronContender('default');
    expect($contender->acquire())->toBeFalse();

    $manager->release('cron.default');
    expect($contender->acquire())->toBeTrue();
    $contender->release();
});

it('keeps the lock held even if the acquiring model instance is destroyed', function () {
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('cron.always'))->toBeTrue();
    unset($manager);

    $contender = cronContender('always');
    expect($contender->acquire())->toBeFalse();

    Mage::getModel('core/lock')->release('cron.always');
    expect($contender->acquire())->toBeTrue();
    $contender->release();
});

it('uses independent locks per mode', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');

    expect($manager->acquire('cron.default'))->toBeTrue();
    expect($manager->acquire('cron.always'))->toBeTrue();

    $manager->release('cron.default');
    $manager->release('cron.always');
});
