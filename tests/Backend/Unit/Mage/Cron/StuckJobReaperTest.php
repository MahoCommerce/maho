<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests the stale-"running" reaper (issue #1154): a schedule whose worker died
 * (OOM, timeout, SIGKILL) is left at status = running forever. reapStuckJobs()
 * flips any running row older than system/cron/max_running_time to error so it
 * is surfaced as a failure and becomes eligible for history pruning.
 */

function makeRunningSchedule(string $executedAt): Mage_Cron_Model_Schedule
{
    $now = Mage::app()->getLocale()->formatDateForDb('now');
    /** @var Mage_Cron_Model_Schedule $schedule */
    $schedule = Mage::getModel('cron/schedule');
    $schedule->setJobCode('unit_test_stuck_job')
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
        ->setCreatedAt($now)
        ->setScheduledAt($executedAt)
        ->setExecutedAt($executedAt)
        ->save();
    return $schedule;
}

afterEach(function () {
    Mage::getModel('cron/schedule')->getCollection()
        ->addFieldToFilter('job_code', 'unit_test_stuck_job')
        ->walk('delete');
});

it('flips a long-running schedule to error', function () {
    $executedAt = Mage::app()->getLocale()->formatDateForDb(time() - 3 * 3600); // 3 hours ago
    $schedule = makeRunningSchedule($executedAt);

    Mage::getModel('cron/observer')->reapStuckJobs();

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect($reloaded->getMessages())->toContain('did not complete');
    expect($reloaded->getFinishedAt())->not->toBeEmpty();
});

it('leaves a recently-started running schedule alone', function () {
    $executedAt = Mage::app()->getLocale()->formatDateForDb(time() - 60); // 1 minute ago
    $schedule = makeRunningSchedule($executedAt);

    Mage::getModel('cron/observer')->reapStuckJobs();

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);
});

it('does nothing when the reaper is disabled', function () {
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Cron_Model_Observer::XML_PATH_MAX_RUNNING_TIME, '0');

    $executedAt = Mage::app()->getLocale()->formatDateForDb(time() - 3 * 3600);
    $schedule = makeRunningSchedule($executedAt);

    Mage::getModel('cron/observer')->reapStuckJobs();

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);

    $store->setConfig(Mage_Cron_Model_Observer::XML_PATH_MAX_RUNNING_TIME, '60');
});
