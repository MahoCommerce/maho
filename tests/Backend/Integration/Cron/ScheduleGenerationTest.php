<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('schedules distinct times from XML and attributes and keeps legacy XML jobs', function (string $compiledExpression): void {
    $configPath = 'crontab/jobs/sitemap_generate/schedule/cron_expr';
    Mage::getConfig()->setNode('default/' . $configPath, '* * * * *');
    Mage::getConfig()->setNode('crontab/jobs/legacy_schedule_test/schedule/cron_expr', '* * * * *');

    $app = Mage::app();
    $app->getStore()->setConfig($configPath, $compiledExpression);
    $app->getStore()->setConfig(Mage_Cron_Model_Observer::XML_PATH_SCHEDULE_AHEAD_FOR, '2');
    $app->removeCache(Mage_Cron_Model_Observer::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT);

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('cron/schedule');
    $jobCodes = ['sitemap_generate', 'legacy_schedule_test'];

    $adapter->beginTransaction();
    try {
        $adapter->delete($table, ['job_code IN (?)' => $jobCodes]);
        Mage::getModel('cron/observer')->generate();

        foreach ($jobCodes as $jobCode) {
            $scheduled = $adapter->fetchCol(
                $adapter->select()->from($table, 'scheduled_at')->where('job_code = ?', $jobCode),
            );
            expect(count($scheduled))->toBeGreaterThanOrEqual(2)
                ->and(array_unique($scheduled))->toHaveCount(count($scheduled));
        }
    } finally {
        $adapter->rollBack();
        $app->removeCache(Mage_Cron_Model_Observer::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT);
    }
})->with([
    'same expression in both sources' => '* * * * *',
    'empty expression in the attribute' => '',
    'additional times from XML' => '*/2 * * * *',
]);
