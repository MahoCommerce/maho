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
 * Coverage for the health check that finds cron jobs left declared after the module
 * that ran them was uninstalled.
 */

/**
 * Run $assert with $rows (path => value) in core_config_data, then put the table back.
 * (scope, scope_id, path) is unique, so an existing path is updated, not inserted.
 *
 * @param array<string, string> $rows
 */
function healthCheckWithCronConfig(array $rows, callable $assert): void
{
    $config = Mage::getConfig();
    $resource = Mage::getSingleton('core/resource');
    $read = $resource->getConnection('core_read');
    $table = $resource->getTableName('core/config_data');

    $previous = [];
    foreach ($rows as $path => $value) {
        $existing = $read->fetchRow(
            $read->select()
                ->from($table, ['value'])
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0)
                ->where('path = ?', $path),
        );
        if ($existing) {
            $previous[$path] = (string) $existing['value'];
        }
        $config->saveConfig($path, $value);
    }
    $config->reinit();

    try {
        $assert();
    } finally {
        foreach (array_keys($rows) as $path) {
            if (isset($previous[$path])) {
                $config->saveConfig($path, $previous[$path]);
            } else {
                $config->deleteConfig($path);
            }
        }
        $config->reinit();
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
 * @param list<array{job_code: string, ...}> $findings
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
                ->and($orphan['paths'])->toBe(["crontab/jobs/{$jobCode}/schedule/cron_expr"])
                ->and($orphan['xml_declared'])->toBeFalse();
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

it('flags a run/model the scheduler rejects even when the class exists', function () {
    $jobCode = 'healthcheck_orphan_' . uniqid();

    healthCheckWithCronConfig(
        // A bare class name resolves via class_exists but fails REGEX_RUN_MODEL at dispatch.
        ["crontab/jobs/{$jobCode}/run/model" => 'Mage_Log_Model_Log::clean'],
        function () use ($jobCode) {
            $orphans = HealthCheck::findOrphanedCronJobs()['orphans'];
            $orphan = current(array_filter($orphans, fn(array $o) => $o['job_code'] === $jobCode));

            expect($orphan)->not->toBeFalse()
                ->and($orphan['reason'])->toContain('model/class::method');
        },
    );
});

it('lets a valid XML run/model win over a stale database override, as dispatch() does', function () {
    $jobCode = 'healthcheck_precedence_' . uniqid();

    healthCheckWithCronConfig(
        ["crontab/jobs/{$jobCode}/run/model" => 'nosuchmodule/observer::run'],
        function () use ($jobCode) {
            Mage::getConfig()->setNode("crontab/jobs/{$jobCode}/run/model", 'core/observer::cleanCache');

            expect(healthCheckCronCodes(HealthCheck::findOrphanedCronJobs()['orphans']))->not->toContain($jobCode);
        },
    );
});

it('flags a job whose XML run node is empty even when a database run/model exists', function () {
    $jobCode = 'healthcheck_emptyrun_' . uniqid();

    healthCheckWithCronConfig(
        ["crontab/jobs/{$jobCode}/run/model" => 'core/observer::cleanCache'],
        function () use ($jobCode) {
            // An existing <run/> is truthy as a SimpleXML element, so dispatch() never
            // falls back to default/ and errors every schedule with "No callbacks found".
            Mage::getConfig()->setNode("crontab/jobs/{$jobCode}/run", '');

            $orphans = HealthCheck::findOrphanedCronJobs()['orphans'];
            $orphan = current(array_filter($orphans, fn(array $o) => $o['job_code'] === $jobCode));

            expect($orphan)->not->toBeFalse()
                ->and($orphan['reason'])->toContain('no run/model')
                ->and($orphan['xml_declared'])->toBeTrue();
        },
    );
});

it('reports an orphan even when its own config_path points back at itself', function () {
    $jobCode = 'healthcheck_selfclaim_' . uniqid();

    healthCheckWithCronConfig(
        [
            "crontab/jobs/{$jobCode}/run/model" => 'nosuchmodule/observer::run',
            "crontab/jobs/{$jobCode}/schedule/config_path" => "crontab/jobs/{$jobCode}/schedule/cron_expr",
        ],
        function () use ($jobCode) {
            expect(healthCheckCronCodes(HealthCheck::findOrphanedCronJobs()['orphans']))->toContain($jobCode);
        },
    );
});

it('flags a run/model resolving to a class that cannot be instantiated', function () {
    $jobCode = 'healthcheck_abstract_' . uniqid();

    healthCheckWithCronConfig(
        ["crontab/jobs/{$jobCode}/run/model" => 'core/abstract::save'],
        function () use ($jobCode) {
            $orphans = HealthCheck::findOrphanedCronJobs()['orphans'];
            $orphan = current(array_filter($orphans, fn(array $o) => $o['job_code'] === $jobCode));

            expect($orphan)->not->toBeFalse()
                ->and($orphan['reason'])->toContain('cannot be instantiated');
        },
    );
});

it('ignores store-scoped config rows the scheduler never reads', function () {
    $jobCode = 'healthcheck_storescope_' . uniqid();
    $path = "crontab/jobs/{$jobCode}/schedule/cron_expr";

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core/config_data');
    $write->insert($table, ['scope' => 'stores', 'scope_id' => 0, 'path' => $path, 'value' => '*/5 * * * *']);

    try {
        $findings = HealthCheck::findOrphanedCronJobs();

        expect(healthCheckCronCodes($findings['orphans']))->not->toContain($jobCode)
            ->and(healthCheckCronCodes($findings['dead']))->not->toContain($jobCode);
    } finally {
        $write->delete($table, ['path = ?' => $path]);
    }
});

it('does not flag a nested run/model left by junk config rows', function () {
    $jobCode = 'healthcheck_nested_' . uniqid();

    healthCheckWithCronConfig(
        [
            "crontab/jobs/{$jobCode}/schedule/cron_expr" => '*/5 * * * *',
            "crontab/jobs/{$jobCode}/run/model/junk" => 'whatever',
        ],
        function () use ($jobCode) {
            $orphans = HealthCheck::findOrphanedCronJobs()['orphans'];
            $orphan = current(array_filter($orphans, fn(array $o) => $o['job_code'] === $jobCode));

            expect($orphan)->not->toBeFalse()
                ->and($orphan['reason'])->toContain('no run/model');
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

it('lists the config rows of a dead job so the purge can delete them', function () {
    $jobCode = 'healthcheck_dead_' . uniqid();
    $path = "crontab/jobs/{$jobCode}/schedule/cron_expr";
    $schedule = healthCheckCronSchedule($jobCode);

    // A store-scoped row never counts as a declaration, so the job stays dead,
    // but the row itself must still be offered for deletion.
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core/config_data');
    $write->insert($table, ['scope' => 'stores', 'scope_id' => 0, 'path' => $path, 'value' => '*/5 * * * *']);

    try {
        $dead = HealthCheck::findOrphanedCronJobs()['wdead'];
        $entry = current(array_filter($dead, fn(array $d) => $d['job_code'] === $jobCode));

        expect($entry)->not->toBeFalse()
            ->and($entry['paths'])->toBe([$path]);
    } finally {
        $write->delete($table, ['path = ?' => $path]);
        $schedule->delete();
    }
});

it('purges the config and schedule rows of an orphaned job', function () {
    $jobCode = 'healthcheck_orphan_' . uniqid();
    $path = "crontab/jobs/{$jobCode}/schedule/cron_expr";
    $schedule = healthCheckCronSchedule($jobCode);

    try {
        healthCheckWithCronConfig(
            [$path => '*/5 * * * *'],
            function () use ($jobCode, $path) {
                expect(healthCheckCronCodes(HealthCheck::findOrphanedCronJobs()['orphans']))->toContain($jobCode);

                [$configRows, $scheduleRows] = HealthCheck::purgeOrphanedCronJobs([$jobCode], [$path]);
                Mage::getConfig()->reinit();

                expect($configRows)->toBe(1)
                    ->and($scheduleRows)->toBe(1)
                    ->and(healthCheckCronCodes(HealthCheck::findOrphanedCronJobs()['orphans']))->not->toContain($jobCode);
            },
        );
    } finally {
        // A no-op after a successful purge; cleans up when an assertion failed first.
        $schedule->delete();
    }
});

it('purges only the paths it is given, never a neighbouring job code', function () {
    $suffix = uniqid();
    $target = "healthcheck_orphan_{$suffix}";
    // Differs from $target only at the underscores a LIKE purge treats as wildcards.
    $neighbour = "healthcheckXorphanX{$suffix}";
    $paths = [
        $target => "crontab/jobs/{$target}/schedule/cron_expr",
        $neighbour => "crontab/jobs/{$neighbour}/schedule/cron_expr",
    ];

    healthCheckWithCronConfig(
        array_fill_keys(array_values($paths), '*/5 * * * *'),
        function () use ($target, $neighbour, $paths) {
            HealthCheck::purgeOrphanedCronJobs([$target], [$paths[$target]]);

            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $surviving = $read->fetchCol(
                $read->select()
                    ->from($resource->getTableName('core/config_data'), ['path'])
                    ->where('path IN (?)', array_values($paths)),
            );
            expect($surviving)->toBe([$paths[$neighbour]]);
        },
    );
});
