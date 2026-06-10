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
 * when the holder goes away (the kernel guarantees the same on process
 * exit or crash).
 */

it('rejects a second holder of the cron lock while held', function () {
    /** @var Mage_Core_Model_Lock $manager */
    $manager = Mage::getModel('core/lock');
    expect($manager->acquire('cron.default'))->toBeTrue();

    /** @var Mage_Core_Model_Lock $contender */
    $contender = Mage::getModel('core/lock');
    expect($contender->acquire('cron.default'))->toBeFalse();

    $manager->release('cron.default');
    expect($contender->acquire('cron.default'))->toBeTrue();
    $contender->release('cron.default');
});

it('uses independent locks per mode', function () {
    /** @var Mage_Core_Model_Lock $default */
    $default = Mage::getModel('core/lock');
    /** @var Mage_Core_Model_Lock $always */
    $always = Mage::getModel('core/lock');

    expect($default->acquire('cron.default'))->toBeTrue();
    expect($always->acquire('cron.always'))->toBeTrue();

    $default->release('cron.default');
    $always->release('cron.always');
});
