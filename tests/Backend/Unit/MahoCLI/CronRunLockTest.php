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
 * Tests the flock-based concurrency guard of cron:run (issue #993).
 * The lock must reject a second holder while held and be released
 * when the owning command is destroyed (the kernel guarantees the
 * same on process exit or crash).
 */

function acquireCronLock(\MahoCLI\Commands\CronRun $command, string $name): bool
{
    $method = new ReflectionMethod($command, 'acquireLock');
    return $method->invoke($command, $name);
}

function cronLockFile(string $name): string
{
    return Mage::getConfig()->getVarDir('locks') . DS . $name . '.lock';
}

it('rejects a second lock holder while the lock is held', function () {
    $command = new \MahoCLI\Commands\CronRun();
    expect(acquireCronLock($command, 'cron.test'))->toBeTrue();

    $contender = fopen(cronLockFile('cron.test'), 'c');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeFalse();

    fclose($contender);
    unset($command);
});

it('releases the lock when the command is destroyed', function () {
    $command = new \MahoCLI\Commands\CronRun();
    expect(acquireCronLock($command, 'cron.test'))->toBeTrue();
    unset($command);

    $contender = fopen(cronLockFile('cron.test'), 'c');
    expect(flock($contender, LOCK_EX | LOCK_NB))->toBeTrue();

    flock($contender, LOCK_UN);
    fclose($contender);
});

it('uses independent locks per mode', function () {
    $default = new \MahoCLI\Commands\CronRun();
    $always = new \MahoCLI\Commands\CronRun();

    expect(acquireCronLock($default, 'cron.default'))->toBeTrue();
    expect(acquireCronLock($always, 'cron.always'))->toBeTrue();

    unset($default, $always);
});
