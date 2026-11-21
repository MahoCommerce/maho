<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

use Mage_Cron_Model_Schedule as Schedule;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Adapter\Pdo\Sqlite;
use Maho\Event\Observer;

uses(Tests\MahoBackendTestCase::class);

class CronHistoryCleanupRecorder
{
    /** @var list<string> */
    public array $loaded = [];

    /** @var array<string, list<string>> */
    public array $deleted = [];

    public function record(Observer $observer): void
    {
        $collection = $observer->getData('collection');
        if ($collection instanceof Mage_Cron_Model_Resource_Schedule_Collection) {
            foreach ($collection->getItems() as $schedule) {
                if (str_starts_with((string) $schedule->getJobCode(), 'history_cleanup_')) {
                    $this->loaded[] = $schedule->getJobCode();
                }
            }
        }

        $object = $observer->getData('object');
        if ($object instanceof Schedule && str_starts_with((string) $object->getJobCode(), 'history_cleanup_')) {
            $this->deleted[$observer->getEvent()->getName()][] = $object->getJobCode();
        }
    }
}

it('loads only expired schedules and deletes them through the model', function (): void {
    $app = Mage::app();
    $app->addEventArea('history_cleanup_test');
    $store = $app->getStore();
    $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'America/New_York');
    $store->setConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_SUCCESS, '60');
    $store->setConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_FAILURE, '600');
    $app->removeCache(Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);

    $deleteEvents = ['model_delete_before', 'model_delete_after', 'model_delete_commit_after'];
    foreach (['core_collection_abstract_load_after', ...$deleteEvents] as $eventName) {
        $path = 'history_cleanup_test/events/' . $eventName . '/observers/record';
        Mage::getConfig()->setNode($path . '/class', CronHistoryCleanupRecorder::class);
        Mage::getConfig()->setNode($path . '/method', 'record');
        Mage::getConfig()->setNode($path . '/type', 'singleton');
    }
    $recorder = Mage::getSingleton(CronHistoryCleanupRecorder::class);

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('cron/schedule');
    $now = time();
    $date = static fn(?int $minutes): ?string => $minutes === null ? null : gmdate('Y-m-d H:i:s', $now - $minutes * 60);
    $schedules = [
        'success_expired' => [Schedule::STATUS_SUCCESS, 62, 63, true],
        'success_recent' => [Schedule::STATUS_SUCCESS, 58, 63, false],
        'failure_expired' => [Schedule::STATUS_ERROR, 602, 603, true],
        'failure_recent' => [Schedule::STATUS_ERROR, 598, 603, false],
        'missed_expired' => [Schedule::STATUS_MISSED, null, 602, true],
        'missed_recent' => [Schedule::STATUS_MISSED, null, 598, false],
        'success_without_execution' => [Schedule::STATUS_SUCCESS, null, 62, true],
        'failure_without_timestamps' => [Schedule::STATUS_ERROR, null, null, false],
        'pending' => [Schedule::STATUS_PENDING, null, 602, false],
        'running' => [Schedule::STATUS_RUNNING, 602, 603, false],
    ];

    $adapter->beginTransaction();
    try {
        $expired = [];
        $retained = [];
        foreach ($schedules as $name => [$status, $executed, $scheduled, $shouldExpire]) {
            $jobCode = 'history_cleanup_' . $name;
            $adapter->insert($table, [
                'job_code' => $jobCode,
                'status' => $status,
                'executed_at' => $date($executed),
                'scheduled_at' => $date($scheduled),
            ]);
            if ($shouldExpire) {
                $expired[] = $jobCode;
            } else {
                $retained[] = $jobCode;
            }
        }

        Mage::getModel('cron/observer')->cleanup();

        $remaining = $adapter->fetchCol(
            $adapter->select()->from($table, 'job_code')->where('job_code IN (?)', [...$expired, ...$retained]),
        );
        expect($remaining)->toEqualCanonicalizing($retained)
            ->and($recorder->loaded)->toEqualCanonicalizing($expired);
        foreach ($deleteEvents as $eventName) {
            expect($recorder->deleted[$eventName])->toEqualCanonicalizing($expired);
        }
    } finally {
        $adapter->rollBack();
        $app->removeCache(Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);
    }
});

it('applies cron history retention to raw timestamps read from a SQL import', function (): void {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    if (!$adapter instanceof Sqlite && !$adapter instanceof Pgsql) {
        $this->markTestSkipped('This test uses SQLite and PostgreSQL timestamp values.');
    }
    $table = $resource->getTableName('cron/schedule');

    $app = Mage::app();
    $app->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_SUCCESS, '60');
    $cacheKey = Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT;
    $app->removeCache($cacheKey);

    $now = time();
    $date = static fn(int $minutes): string => gmdate('Y-m-d H:i:s', $now - $minutes * 60);
    $schedules = $adapter instanceof Sqlite ? [
        'sqlite_blank_recent' => ['', $date(30), true],
        'sqlite_blank_expired' => ['', $date(90), false],
        'sqlite_zero_recent' => ['0', $date(30), true],
        'sqlite_no_reference' => [null, '', true],
        'sqlite_offset_recent' => [$date(150) . '-02:00', null, true],
        'sqlite_offset_expired' => [$date(-30) . '+02:00', null, false],
    ] : [
        'pgsql_infinity_executed' => ['infinity', $date(30), false],
        'pgsql_infinity_scheduled' => [null, 'infinity', false],
        'pgsql_negative_infinity' => ['-infinity', null, false],
        'pgsql_large_year' => ['10000-01-01 00:00:00', null, false],
        'pgsql_four_digit_year' => ['9999-12-31 23:59:59', null, true],
    ];

    $adapter->beginTransaction();
    try {
        $retained = [];
        foreach ($schedules as $jobCode => [$executedAt, $scheduledAt, $shouldKeep]) {
            $adapter->insert($table, [
                'job_code' => $jobCode,
                'status' => Schedule::STATUS_SUCCESS,
                'executed_at' => $executedAt,
                'scheduled_at' => $scheduledAt,
            ]);
            if ($shouldKeep) {
                $retained[] = $jobCode;
            }
        }

        Mage::getModel('cron/observer')->cleanup();

        $remaining = $adapter->fetchCol(
            $adapter->select()->from($table, 'job_code')->where('job_code IN (?)', array_keys($schedules)),
        );
        expect($remaining)->toEqualCanonicalizing($retained);
    } finally {
        $adapter->rollBack();
        $app->removeCache($cacheKey);
    }
});

it('keeps history when the retention cutoff falls outside the SQL date range', function (): void {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('cron/schedule');
    $app = Mage::app();
    $app->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_SUCCESS, '2000000000');
    $cacheKey = Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT;
    $app->removeCache($cacheKey);

    $adapter->beginTransaction();
    try {
        $jobCode = 'history_cleanup_long_retention';
        $adapter->insert($table, [
            'job_code' => $jobCode,
            'status' => Schedule::STATUS_SUCCESS,
            'executed_at' => '2000-01-01 00:00:00',
        ]);

        Mage::getModel('cron/observer')->cleanup();

        $remaining = $adapter->fetchOne(
            $adapter->select()->from($table, 'job_code')->where('job_code = ?', $jobCode),
        );
        expect($remaining)->toBe($jobCode);
    } finally {
        $adapter->rollBack();
        $app->removeCache($cacheKey);
    }
});
