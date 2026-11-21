<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function runCurrencyCronBackend(mixed $time, int|string $frequency = 1): Mage_Adminhtml_Model_System_Config_Backend_Currency_Cron
{
    $model = Mage::getModel('adminhtml/system_config_backend_currency_cron');
    $model->setData('groups/import/fields/time/value', $time);
    $model->setData('groups/import/fields/frequency/value', $frequency);
    (new ReflectionMethod($model, '_afterSave'))->invoke($model);
    return $model;
}

it('rejects a scalar currency import time before writing cron configuration', function () {
    $path = Mage_Adminhtml_Model_System_Config_Backend_Currency_Cron::CRON_STRING_PATH;
    $before = Mage::getModel('core/config_data')->load($path, 'path')->getValue();

    expect(fn() => runCurrencyCronBackend(12))
        ->toThrow(Mage_Core_Exception::class, 'valid hour and minute');

    expect(Mage::getModel('core/config_data')->load($path, 'path')->getValue())->toBe($before);
});

it('rejects an out-of-range currency import time', function (array $time) {
    expect(fn() => runCurrencyCronBackend($time))
        ->toThrow(Mage_Core_Exception::class, 'valid hour and minute');
})->with([
    'negative hour' => [[-1, 0]],
    'hour above 23' => [[24, 0]],
    'negative minute' => [[0, -1]],
    'minute above 59' => [[0, 60]],
]);

it('writes a valid currency import schedule', function () {
    runCurrencyCronBackend([7, 5], Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY);

    expect(Mage::getModel('core/config_data')
        ->load(Mage_Adminhtml_Model_System_Config_Backend_Currency_Cron::CRON_STRING_PATH, 'path')
        ->getValue())->toBe('5 7 1 * *');
});
