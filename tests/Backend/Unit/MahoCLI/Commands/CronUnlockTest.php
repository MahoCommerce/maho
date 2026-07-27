<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CronUnlock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the cron:unlock command (issue #1154): resetting stale "running"
 * schedules left behind by a crashed or killed worker. Without --all only rows
 * older than max_running_time are cleared; --all clears every running row; an
 * optional job_code narrows the scope.
 */

function cronUnlockTester(): CommandTester
{
    $command = new CronUnlock('cron:unlock');
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command);
}

function makeUnlockSchedule(string $jobCode, int $executedAgo): Mage_Cron_Model_Schedule
{
    $locale = Mage::app()->getLocale();
    $ts = $locale->formatDateForDb(time() - $executedAgo);
    /** @var Mage_Cron_Model_Schedule $schedule */
    $schedule = Mage::getModel('cron/schedule');
    $schedule->setJobCode($jobCode)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
        ->setCreatedAt($ts)
        ->setScheduledAt($ts)
        ->setExecutedAt($ts)
        ->save();
    return $schedule;
}

afterEach(function () {
    Mage::app()->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_MAX_RUNNING_TIME, '60');
    foreach (['unit_test_unlock_a', 'unit_test_unlock_b'] as $jobCode) {
        Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('job_code', $jobCode)
            ->walk('delete');
    }
});

it('unlocks only schedules older than max_running_time by default', function () {
    $stale = makeUnlockSchedule('unit_test_unlock_a', 3 * 3600); // 3 hours ago
    $fresh = makeUnlockSchedule('unit_test_unlock_b', 60);       // 1 minute ago

    $tester = cronUnlockTester();
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect(Mage::getModel('cron/schedule')->load($stale->getId())->getStatus())
        ->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect(Mage::getModel('cron/schedule')->load($fresh->getId())->getStatus())
        ->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);
});

it('unlocks every running schedule with --all regardless of age', function () {
    $fresh = makeUnlockSchedule('unit_test_unlock_a', 60);

    $tester = cronUnlockTester();
    $tester->execute(['--all' => true]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $reloaded = Mage::getModel('cron/schedule')->load($fresh->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect($reloaded->getFinishedAt())->not->toBeEmpty();
});

it('restricts unlocking to the given job_code', function () {
    $target = makeUnlockSchedule('unit_test_unlock_a', 60);
    $other = makeUnlockSchedule('unit_test_unlock_b', 60);

    $tester = cronUnlockTester();
    $tester->execute(['job_code' => 'unit_test_unlock_a', '--all' => true]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect(Mage::getModel('cron/schedule')->load($target->getId())->getStatus())
        ->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect(Mage::getModel('cron/schedule')->load($other->getId())->getStatus())
        ->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);
});

it('reports when no stale running schedules are found', function () {
    makeUnlockSchedule('unit_test_unlock_a', 60); // fresh, below the default threshold

    $tester = cronUnlockTester();
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('No stale running schedules found');
});
