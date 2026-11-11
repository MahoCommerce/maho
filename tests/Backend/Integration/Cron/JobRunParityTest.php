<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\CronRun;
use MahoCLI\Commands\CronUnlock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Run Now action of System > Tools > Cron Jobs and cron:run <job_code> run a job through Mage_Cron_Service_Job
 * (#1348). The job holds a lock while it runs, so that two runs of one job never overlap: not from the admin, the
 * CLI or the scheduler. cron:unlock and the reaper leave the schedule of a job that holds its lock.
 */

const CRON_PARITY_JOB = 'cron_parity_job';

/**
 * The model of the test job. Each run calls $onRun with the schedule.
 */
class CronParity_Model_Job
{
    /** @var (Closure(Mage_Cron_Model_Schedule): void)|null */
    public static ?Closure $onRun = null;

    public static int $runs = 0;

    public function execute(Mage_Cron_Model_Schedule $schedule): void
    {
        self::$runs++;
        if (self::$onRun !== null) {
            (self::$onRun)($schedule);
        }
    }
}

class CronParityObserver extends Mage_Cron_Model_Observer
{
    public function runScheduled(Mage_Cron_Model_Schedule $schedule, callable $callback): self
    {
        return $this->_executeScheduledJob($schedule, $callback);
    }
}

/**
 * @return array{error?: bool, message?: string}
 */
function cronParityAdmin(string $jobCode): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/admin/system_tools_cronjobs/run', 'POST', ['job_code' => $jobCode]),
    );
    $request->setRouteName('adminhtml')->setControllerName('system_tools_cronjobs')->setActionName('run')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Cron_Adminhtml_System_Tools_CronjobsController($request, $response)->runAction();
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param array<string, mixed> $input
 * @return array{code: int, output: string}
 */
function cronParityCli(Command $command, array $input): array
{
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $code = $tester->execute($input, ['interactive' => false]);
    return ['code' => $code, 'output' => $tester->getDisplay()];
}

/**
 * Hold the lock of $jobCode from outside this process, as a run in another process does.
 *
 * @return resource
 */
function cronParityHoldLock(string $jobCode)
{
    $handle = fopen(Mage::getConfig()->getVarDir('locks') . DS . "cron.job.{$jobCode}.lock", 'c');
    expect(flock($handle, LOCK_EX | LOCK_NB))->toBeTrue();
    return $handle;
}

/**
 * @return list<Mage_Cron_Model_Schedule>
 */
function cronParitySchedules(): array
{
    return array_values(Mage::getModel('cron/schedule')->getCollection()
        ->addFieldToFilter('job_code', CRON_PARITY_JOB)
        ->setOrder('schedule_id', 'ASC')
        ->getItems());
}

beforeEach(function (): void {
    Mage::getConfig()->setNode('global/models/cronparity/class', 'CronParity_Model');
    Mage::getConfig()->setNode('crontab/jobs/' . CRON_PARITY_JOB . '/run/model', 'cronparity/job::execute');
    CronParity_Model_Job::$onRun = null;
    CronParity_Model_Job::$runs = 0;
});

afterEach(function (): void {
    Mage::getModel('cron/schedule')->getCollection()->addFieldToFilter('job_code', CRON_PARITY_JOB)->walk('delete');
});

it('refuses a job that no module declares, on the admin and the CLI', function (): void {
    $message = Mage::helper('cron')->__('Unknown cron job: %s', 'cron_parity_missing');

    $admin = cronParityAdmin('cron_parity_missing');
    $cli = cronParityCli(new CronRun(), ['mode' => 'cron_parity_missing']);

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain($message);
});

it('refuses a job whose callback does not exist, on the admin and the CLI', function (): void {
    Mage::getConfig()->setNode('crontab/jobs/' . CRON_PARITY_JOB . '/run/model', 'cronparity/job::missing');
    $message = Mage::helper('cron')->__('Invalid callback: %s::%s does not exist', 'cronparity/job', 'missing');

    $admin = cronParityAdmin(CRON_PARITY_JOB);
    $cli = cronParityCli(new CronRun(), ['mode' => CRON_PARITY_JOB]);

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain($message)
        ->and(cronParitySchedules())->toBe([]);
});

it('refuses to start a job that another process runs, on the admin and the CLI', function (): void {
    $lock = cronParityHoldLock(CRON_PARITY_JOB);
    $message = Mage::helper('cron')->__('The cron job %s is already running.', CRON_PARITY_JOB);

    try {
        $admin = cronParityAdmin(CRON_PARITY_JOB);
        $cli = cronParityCli(new CronRun(), ['mode' => CRON_PARITY_JOB]);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    expect($admin)->toBe(['error' => true, 'message' => $message])
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message)
        ->and(CronParity_Model_Job::$runs)->toBe(0)
        ->and(cronParitySchedules())->toBe([]);
});

it('records a run of the CLI in a new schedule, and frees the lock after it', function (): void {
    $cli = cronParityCli(new CronRun(), ['mode' => CRON_PARITY_JOB]);

    $schedules = cronParitySchedules();
    expect($cli['code'])->toBe(Command::SUCCESS)
        ->and(CronParity_Model_Job::$runs)->toBe(1)
        ->and($schedules)->toHaveCount(1)
        ->and($schedules[0]->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_SUCCESS)
        ->and($schedules[0]->getFinishedAt())->not->toBeNull()
        ->and(Mage::getService('cron/job')->isRunning(CRON_PARITY_JOB))->toBeFalse();
});

it('records the error of a job and frees the lock after it', function (): void {
    CronParity_Model_Job::$onRun = function (): void {
        throw new RuntimeException('cron parity failure');
    };

    $cli = cronParityCli(new CronRun(), ['mode' => CRON_PARITY_JOB]);

    $schedules = cronParitySchedules();
    expect($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain('cron parity failure')
        ->and($schedules[0]->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR)
        ->and($schedules[0]->getMessages())->toBe('cron parity failure')
        ->and(Mage::getService('cron/job')->isRunning(CRON_PARITY_JOB))->toBeFalse();
});

it('does not start a second run of a job while the first one runs', function (): void {
    /** @var Mage_Cron_Service_Job $service */
    $service = Mage::getService('cron/job');
    $secondRun = null;
    CronParity_Model_Job::$onRun = function () use ($service, &$secondRun): void {
        CronParity_Model_Job::$onRun = null;
        try {
            $service->run(CRON_PARITY_JOB);
        } catch (Mage_Core_Exception_Conflict $e) {
            $secondRun = $e->getMessage();
        }
    };

    $schedule = $service->run(CRON_PARITY_JOB);

    expect($secondRun)->toBe(Mage::helper('cron')->__('The cron job %s is already running.', CRON_PARITY_JOB))
        ->and(CronParity_Model_Job::$runs)->toBe(1)
        ->and($schedule->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_SUCCESS);
});

it('lets the scheduler skip a pending schedule while a run of its job holds the lock', function (): void {
    $now = Mage::app()->getLocale()->formatDateForDb('now');
    /** @var Mage_Cron_Model_Schedule $pending */
    $pending = Mage::getModel('cron/schedule');
    $pending->setJobCode(CRON_PARITY_JOB)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
        ->setCreatedAt($now)
        ->setScheduledAt($now)
        ->save();
    $observer = new CronParityObserver();
    CronParity_Model_Job::$onRun = function () use ($observer, $pending): void {
        $observer->runScheduled($pending, [new CronParity_Model_Job(), 'execute']);
    };

    Mage::getService('cron/job')->run(CRON_PARITY_JOB);

    expect(CronParity_Model_Job::$runs)->toBe(1)
        ->and(Mage::getModel('cron/schedule')->load($pending->getId())->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_PENDING);

    $observer->runScheduled($pending, [new CronParity_Model_Job(), 'execute']);
    expect(CronParity_Model_Job::$runs)->toBe(2)
        ->and(Mage::getModel('cron/schedule')->load($pending->getId())->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_SUCCESS);
});

it('stores the message of the exception for a run of the scheduler, as for a run now', function (): void {
    $now = Mage::app()->getLocale()->formatDateForDb('now');
    /** @var Mage_Cron_Model_Schedule $pending */
    $pending = Mage::getModel('cron/schedule');
    $pending->setJobCode(CRON_PARITY_JOB)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
        ->setCreatedAt($now)
        ->setScheduledAt($now)
        ->save();
    CronParity_Model_Job::$onRun = function (): void {
        throw new RuntimeException('cron parity failure');
    };

    new CronParityObserver()->runScheduled($pending, Mage::getService('cron/job')->resolveCallback('cronparity/job::execute'));
    $runNow = Mage::getService('cron/job')->run(CRON_PARITY_JOB);

    $scheduled = Mage::getModel('cron/schedule')->load($pending->getId());
    expect($scheduled->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR)
        ->and($scheduled->getMessages())->toBe('cron parity failure')
        ->and($runNow->getMessages())->toBe($scheduled->getMessages());
});

it('lets cron:unlock reset a stale schedule, but not the schedule of a job that runs', function (): void {
    $now = Mage::app()->getLocale()->formatDateForDb('now');
    /** @var Mage_Cron_Model_Schedule $stale */
    $stale = Mage::getModel('cron/schedule');
    $stale->setJobCode(CRON_PARITY_JOB)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
        ->setCreatedAt($now)
        ->setScheduledAt($now)
        ->setExecutedAt($now)
        ->save();

    $lock = cronParityHoldLock(CRON_PARITY_JOB);
    try {
        $whileRunning = cronParityCli(new CronUnlock(), ['job_code' => CRON_PARITY_JOB, '--all' => true]);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    expect($whileRunning['output'])->toContain('No stale running schedules found')
        ->and(Mage::getModel('cron/schedule')->load($stale->getId())->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);

    $afterRun = cronParityCli(new CronUnlock(), ['job_code' => CRON_PARITY_JOB, '--all' => true]);
    expect($afterRun['code'])->toBe(Command::SUCCESS)
        ->and(Mage::getModel('cron/schedule')->load($stale->getId())->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR);
});

it('lets the reaper reset a stuck schedule, but not the schedule of a job that runs', function (): void {
    $startedAt = Mage::app()->getLocale()->formatDateForDb(time() - 3 * 3600);
    /** @var Mage_Cron_Model_Schedule $stuck */
    $stuck = Mage::getModel('cron/schedule');
    $stuck->setJobCode(CRON_PARITY_JOB)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
        ->setCreatedAt($startedAt)
        ->setScheduledAt($startedAt)
        ->setExecutedAt($startedAt)
        ->save();
    Mage::app()->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_MAX_RUNNING_TIME, '60');

    $lock = cronParityHoldLock(CRON_PARITY_JOB);
    try {
        new Mage_Cron_Model_Observer()->reapStuckJobs();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    expect(Mage::getModel('cron/schedule')->load($stuck->getId())->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_RUNNING);

    new Mage_Cron_Model_Observer()->reapStuckJobs();
    $reaped = Mage::getModel('cron/schedule')->load($stuck->getId());
    expect($reaped->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_ERROR)
        ->and($reaped->getMessages())->toContain('did not complete');
});
