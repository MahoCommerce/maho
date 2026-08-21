<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\HealthCheck;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the health check that finds cron declarations outliving their code.
 * Uninstalling a module leaves its crontab/jobs rows in core_config_data, and the
 * scheduler keeps generating cron_schedule rows for them: generate() only reads the
 * schedule expression, dispatch() skips a job with no run/model, and nothing ever
 * deletes a pending row.
 */

/**
 * Run $assert with $rows (path => value) present in core_config_data at default scope,
 * restoring whatever was there before. (scope, scope_id, path) is unique, so a path the
 * install already configures must be updated rather than inserted into.
 *
 * @param array<string, string> $rows
 */
function healthCheckWithCronConfig(array $rows, callable $assert): void
{
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core/config_data');

    $previous = [];
    foreach ($rows as $path => $value) {
        $existing = $write->fetchRow(
            $write->select()
                ->from($table, ['config_id', 'value'])
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0)
                ->where('path = ?', $path),
        );

        if ($existing) {
            $previous[$path] = (string) $existing['value'];
            $write->update($table, ['value' => $value], ['config_id = ?' => (int) $existing['config_id']]);
        } else {
            $write->insert($table, ['scope' => 'default', 'scope_id' => 0, 'path' => $path, 'value' => $value]);
        }
    }
    Mage::getConfig()->reinit();

    try {
        $assert();
    } finally {
        foreach ($rows as $path => $value) {
            if (isset($previous[$path])) {
                $write->update($table, ['value' => $previous[$path]], ['scope = ?' => 'default', 'scope_id = ?' => 0, 'path = ?' => $path]);
            } else {
                $write->delete($table, ['scope = ?' => 'default', 'scope_id = ?' => 0, 'path = ?' => $path]);
            }
        }
        Mage::getConfig()->reinit();
    }
}

function healthCheckCronSchedule(string $jobCode): Mage_Cron_Model_Schedule
{
    /** @var Mage_Cron_Model_Schedule $schedule */
    $schedule = Mage::getModel('cron/schedule');
    $schedule->setJobCode($jobCode)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
        ->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'))
        ->setScheduledAt(Mage::app()->getLocale()->formatDateForDb('now'))
        ->save();

    return $schedule;
}

/**
 * @param list<array{job_code: string}> $findings
 * @return list<string>
 */
function healthCheckCronCodes(array $findings): array
{
    return array_column($findings, 'job_code');
}

it('reports no orphaned or stale cron jobs on a healthy install', function () {
    $findings = HealthCheck::findOrphanedCronJobs();

    expect($findings['orphans'])->toBe([])
        ->and($findings['stale'])->toBe([]);
});

it('flags a database-declared job with no code behind it', function () {
    $jobCode = 'healthcheck_orphan_' . uniqid();

    healthCheckWithCronConfig(
        ["crontab/jobs/{$jobCode}/schedule/cron_expr" => '*/5 * * * *'],
        function () use ($jobCode) {
            $findings = HealthCheck::findOrphanedCronJobs();

            expect(healthCheckCronCodes($findings['orphans']))->toContain($jobCode);

            $orphan = current(array_filter($findings['orphans'], fn(array $o) => $o['job_code'] === $jobCode));
            expect($orphan['reason'])->toContain('no run/model')
                ->and($orphan['paths'])->toBe(["crontab/jobs/{$jobCode}/schedule/cron_expr"]);
        },
    );
});

it('flags a job whose run/model points at a class that no longer exists', function () {
    $jobCode = 'healthcheck_orphan_' . uniqid();

    healthCheckWithCronConfig(
        [
            "crontab/jobs/{$jobCode}/schedule/cron_expr" => '*/5 * * * *',
            "crontab/jobs/{$jobCode}/run/model" => 'nosuchmodule/observer::run',
        ],
        function () use ($jobCode) {
            $orphans = HealthCheck::findOrphanedCronJobs()['orphans'];
            $orphan = current(array_filter($orphans, fn(array $o) => $o['job_code'] === $jobCode));

            expect($orphan)->not->toBeFalse()
                ->and($orphan['reason'])->toContain('does not exist')
                ->and($orphan['model'])->toBe('nosuchmodule/observer::run');
        },
    );
});

it('leaves a database schedule override of an attribute-registered job alone', function () {
    healthCheckWithCronConfig(
        ['crontab/jobs/sitemap_generate/schedule/cron_expr' => '0 3 * * *'],
        function () {
            $findings = HealthCheck::findOrphanedCronJobs();

            expect(healthCheckCronCodes($findings['orphans']))->not->toContain('sitemap_generate')
                ->and(healthCheckCronCodes($findings['disabled']))->not->toContain('sitemap_generate');
        },
    );
});

it('counts the schedule rows an orphaned job keeps generating', function () {
    $jobCode = 'healthcheck_orphan_' . uniqid();
    $schedule = healthCheckCronSchedule($jobCode);

    try {
        healthCheckWithCronConfig(
            ["crontab/jobs/{$jobCode}/schedule/cron_expr" => '*/5 * * * *'],
            function () use ($jobCode) {
                $orphans = HealthCheck::findOrphanedCronJobs()['orphans'];
                $orphan = current(array_filter($orphans, fn(array $o) => $o['job_code'] === $jobCode));

                expect($orphan['schedules'])->toBe(1);
            },
        );
    } finally {
        $schedule->delete();
    }
});

it('flags schedule rows for a job code nothing declares', function () {
    $jobCode = 'healthcheck_dead_' . uniqid();
    $schedule = healthCheckCronSchedule($jobCode);

    try {
        $findings = HealthCheck::findOrphanedCronJobs();

        expect(healthCheckCronCodes($findings['dead']))->toContain($jobCode)
            ->and(healthCheckCronCodes($findings['orphans']))->not->toContain($jobCode);
    } finally {
        $schedule->delete();
    }
});

it('purges the config and schedule rows of an orphaned job', function () {
    $jobCode = 'healthcheck_orphan_' . uniqid();
    $path = "crontab/jobs/{$jobCode}/schedule/cron_expr";

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $write->insert($resource->getTableName('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => $path,
        'value' => '*/5 * * * *',
    ]);
    healthCheckCronSchedule($jobCode);
    Mage::getConfig()->reinit();

    expect(healthCheckCronCodes(HealthCheck::findOrphanedCronJobs()['orphans']))->toContain($jobCode);

    [$configRows, $scheduleRows] = HealthCheck::purgeOrphanedCronJobs([$jobCode], [$path]);
    Mage::getConfig()->reinit();

    expect($configRows)->toBe(1)
        ->and($scheduleRows)->toBe(1)
        ->and(healthCheckCronCodes(HealthCheck::findOrphanedCronJobs()['orphans']))->not->toContain($jobCode);
});

it('purges only the paths it is given, never a neighbouring job code', function () {
    $suffix = uniqid();
    $target = "healthcheck_orphan_{$suffix}";
    // Differs from $target only where it has an underscore: a LIKE-based purge would
    // treat that underscore as a wildcard and delete this job's rows too.
    $neighbour = "healthcheckXorphanX{$suffix}";

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core/config_data');

    $paths = [];
    foreach ([$target, $neighbour] as $jobCode) {
        $paths[$jobCode] = "crontab/jobs/{$jobCode}/schedule/cron_expr";
        $write->insert($table, [
            'scope' => 'default',
            'scope_id' => 0,
            'path' => $paths[$jobCode],
            'value' => '*/5 * * * *',
        ]);
    }

    try {
        HealthCheck::purgeOrphanedCronJobs([$target], [$paths[$target]]);

        $surviving = $write->fetchCol(
            $write->select()->from($table, ['path'])->where('path IN (?)', array_values($paths)),
        );
        expect($surviving)->toBe([$paths[$neighbour]]);
    } finally {
        $write->delete($table, ['path IN (?)' => array_values($paths)]);
        Mage::getConfig()->reinit();
    }
});
