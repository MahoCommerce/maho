<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

use Mage_Cron_Model_Schedule as Schedule;

uses(Tests\MahoBackendTestCase::class);

it('deletes only the history entries older than their retention period', function (string $failureLifetime, bool $oldFailuresExpire, bool $recentFailuresExpire): void {
    $app = Mage::app();
    $app->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_SUCCESS, '60');
    $app->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_FAILURE, $failureLifetime);
    $app->removeCache(Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('cron/schedule');
    $now = time();
    $date = static fn(?int $minutes): ?string => $minutes === null ? null : gmdate('Y-m-d H:i:s', $now - $minutes * 60);

    // job code => [status, executed_at minutes ago, scheduled_at minutes ago, expired]
    $schedules = [
        'success_expired' => [Schedule::STATUS_SUCCESS, 62, 63, true],
        'success_recent' => [Schedule::STATUS_SUCCESS, 58, 63, false],
        'success_not_executed' => [Schedule::STATUS_SUCCESS, null, 62, true],
        'error_expired' => [Schedule::STATUS_ERROR, 602, 603, $oldFailuresExpire],
        'error_recent' => [Schedule::STATUS_ERROR, 598, 603, $recentFailuresExpire],
        'error_without_dates' => [Schedule::STATUS_ERROR, null, null, false],
        'missed_expired' => [Schedule::STATUS_MISSED, null, 602, $oldFailuresExpire],
        'missed_recent' => [Schedule::STATUS_MISSED, null, 598, $recentFailuresExpire],
        'pending' => [Schedule::STATUS_PENDING, null, 602, false],
        'running' => [Schedule::STATUS_RUNNING, 602, 603, false],
    ];

    $adapter->beginTransaction();
    try {
        $retained = [];
        foreach ($schedules as $name => [$status, $executed, $scheduled, $expired]) {
            $adapter->insert($table, [
                'job_code' => 'history_cleanup_' . $name,
                'status' => $status,
                'executed_at' => $date($executed),
                'scheduled_at' => $date($scheduled),
            ]);
            if (!$expired) {
                $retained[] = 'history_cleanup_' . $name;
            }
        }

        Mage::getModel('cron/observer')->cleanup();

        $remaining = $adapter->fetchCol(
            $adapter->select()->from($table, 'job_code')->where('job_code LIKE ?', 'history_cleanup_%'),
        );
        sort($remaining);
        sort($retained);
        expect($remaining)->toBe($retained);
    } finally {
        $adapter->rollBack();
        $app->removeCache(Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);
    }
})->with([
    'ordinary retention' => ['600', true, false],
    'failure retention before the SQL date range' => ['2000000000', false, false],
    'zero failure retention' => ['0', true, true],
]);
