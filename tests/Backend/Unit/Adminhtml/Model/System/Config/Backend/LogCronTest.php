<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Behaviour of the Log group's cron backend, which runs when the System configuration
 * section is saved (e.g. when activating SMTP, since the Log group lives in that same
 * section).
 *
 * Two things were fixed here:
 *  - The backend used to copy crontab/jobs/log_clean/run/model via Mage::getConfig()
 *    ->getNode(), which was dead code (the job's model comes from its #[CronJob]
 *    attribute) and forced a config-section load inside the save transaction. That copy
 *    was removed.
 *  - The shipped default for the start time is empty (<time/>), which used to raise an
 *    "array offset on null" warning; time parsing is now null-safe.
 *
 * The transaction-safety of the underlying advisory lock is covered separately by the
 * adapter-level test in Db/Adapter/TransactionHandlingTest.php.
 */
function saveSystemSectionWithLog(array $logFields): void
{
    $data = new Mage_Adminhtml_Model_Config_Data();
    $data->setSection('system')
        ->setWebsite('')
        ->setStore('')
        ->setGroups([
            'smtp' => ['fields' => ['enabled' => ['value' => 'smtp']]],
            'log'  => ['fields' => $logFields],
        ]);
    $data->save();
}

function logCronExpr(): string
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $select = $adapter->select()
        ->from('core_config_data', ['value'])
        ->where('path = ?', Mage_Adminhtml_Model_System_Config_Backend_Log_Cron::CRON_STRING_PATH);
    return (string) $adapter->fetchOne($select);
}

beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->delete('core_config_data', "path LIKE 'crontab/jobs/log_clean/%'");
});

it('builds the cron expression from the configured start time and frequency', function () {
    saveSystemSectionWithLog([
        'enable_log' => ['value' => '2'],
        'enabled'    => ['value' => '1'],
        'time'       => ['value' => ['3', '30', '0']], // 03:30
        'frequency'  => ['value' => 'W'],              // weekly
    ]);

    // minute hour dom month dow  ->  30 3 * * 1
    expect(logCronExpr())->toBe('30 3 * * 1');
});

it('defaults an empty start time to midnight instead of erroring', function () {
    // The shipped default is an empty <time/>; this must not raise a warning or abort.
    saveSystemSectionWithLog([
        'enable_log' => ['value' => '2'],
        'enabled'    => ['value' => '1'],
        'time'       => ['value' => ['', '', '']],
        'frequency'  => ['value' => 'D'],
    ]);

    expect(logCronExpr())->toBe('0 0 * * *');
});

it('does not choke when the time field is missing entirely', function () {
    saveSystemSectionWithLog([
        'enable_log' => ['value' => '2'],
        'enabled'    => ['value' => '1'],
        'frequency'  => ['value' => 'D'],
    ]);

    expect(logCronExpr())->toBe('0 0 * * *');
});

it('clears the schedule when log cleaning is disabled', function () {
    saveSystemSectionWithLog([
        'enable_log' => ['value' => '2'],
        'enabled'    => ['value' => '0'],
        'time'       => ['value' => ['3', '30', '0']],
        'frequency'  => ['value' => 'D'],
    ]);

    expect(logCronExpr())->toBe('');
});
