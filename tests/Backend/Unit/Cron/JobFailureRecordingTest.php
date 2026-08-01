<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests the two in-process layers of the stuck-job fix (issue #1154): a job throwing a
 * \Throwable that is not an Exception (TypeError, ValueError, Error, ...) used to propagate
 * past the handler and leave the row at status = running, and a fatal error (OOM,
 * max_execution_time) is recorded by the shutdown handler that no try/catch can reach.
 */

/**
 * Exposes the protected execution helpers so the failure paths can be driven directly,
 * without a real crontab entry or an actually-fatal process.
 */
class CronFailureRecordingObserver extends Mage_Cron_Model_Observer
{
    public function runCallback(Mage_Cron_Model_Schedule $schedule, callable $callback): self
    {
        return $this->_executeScheduledJob($schedule, $callback);
    }

    /**
     * @param array{type: int, message: string, file: string, line: int}|null $error
     */
    public function recordFatal(Mage_Cron_Model_Schedule $schedule, ?array $error): void
    {
        $this->_recordFatalError($schedule, $error);
    }
}

function cronFailureObserver(): CronFailureRecordingObserver
{
    return new CronFailureRecordingObserver();
}

function makePendingSchedule(): Mage_Cron_Model_Schedule
{
    $now = Mage::app()->getLocale()->formatDateForDb('now');
    /** @var Mage_Cron_Model_Schedule $schedule */
    $schedule = Mage::getModel('cron/schedule');
    $schedule->setJobCode('unit_test_failing_job')
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
        ->setCreatedAt($now)
        ->setScheduledAt($now)
        ->save();
    return $schedule;
}

afterEach(function () {
    Mage::getModel('cron/schedule')->getCollection()
        ->addFieldToFilter('job_code', 'unit_test_failing_job')
        ->walk('delete');
});

it('records an error when a job throws an Error rather than an Exception', function () {
    $schedule = makePendingSchedule();

    cronFailureObserver()->runCallback($schedule, function (): void {
        throw new TypeError('unit test type error');
    });

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect($reloaded->getMessages())->toContain('unit test type error');
});

it('still records an error when a job throws an Exception', function () {
    $schedule = makePendingSchedule();

    cronFailureObserver()->runCallback($schedule, function (): void {
        throw new RuntimeException('unit test runtime exception');
    });

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect($reloaded->getMessages())->toContain('unit test runtime exception');
});

it('records a fatal error against a schedule still marked running', function () {
    $schedule = makePendingSchedule();
    $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)->save();

    cronFailureObserver()->recordFatal($schedule, [
        'type' => E_ERROR,
        'message' => 'Allowed memory size of 1 bytes exhausted',
        'file' => '/unit/test.php',
        'line' => 42,
    ]);

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
    expect($reloaded->getMessages())->toContain('Allowed memory size of 1 bytes exhausted');
    expect($reloaded->getMessages())->toContain('/unit/test.php:42');
    expect($reloaded->getFinishedAt())->not->toBeEmpty();
});

it('ignores a non-fatal last error', function () {
    $schedule = makePendingSchedule();
    $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)->save();

    cronFailureObserver()->recordFatal($schedule, [
        'type' => E_WARNING,
        'message' => 'just a warning',
        'file' => '/unit/test.php',
        'line' => 42,
    ]);

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);
    expect($reloaded->getMessages())->toBeEmpty();
});

it('ignores a schedule that already carries an outcome', function () {
    $schedule = makePendingSchedule();
    $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_SUCCESS)->save();

    cronFailureObserver()->recordFatal($schedule, [
        'type' => E_ERROR,
        'message' => 'another job died',
        'file' => '/unit/test.php',
        'line' => 42,
    ]);

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_SUCCESS);
});

it('does nothing when there was no last error', function () {
    $schedule = makePendingSchedule();
    $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)->save();

    cronFailureObserver()->recordFatal($schedule, null);

    $reloaded = Mage::getModel('cron/schedule')->load($schedule->getId());
    expect($reloaded->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);
});
