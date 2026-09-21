<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cron
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A pending schedule can name a job code that no compiled attribute declares, for example after
 * a module removal or a job rename. No module declares a top-level <crontab> node any more, so
 * both job roots are false. dispatch() must skip such a row instead of reading a property on
 * false. See https://github.com/MahoCommerce/maho/issues/1402
 */

const CRON_UNKNOWN_JOB_CODE = 'unit_test_unknown_job';

afterEach(function () {
    foreach (Mage::getModel('cron/schedule')->getCollection()
        ->addFieldToFilter('job_code', CRON_UNKNOWN_JOB_CODE) as $schedule) {
        $schedule->delete();
    }
});

it('skips a schedule whose job code has no config node, and raises no warning', function () {
    $now = Mage::app()->getLocale()->formatDateForDb('now');
    Mage::getModel('cron/schedule')
        ->setJobCode(CRON_UNKNOWN_JOB_CODE)
        ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
        ->setCreatedAt($now)
        ->setScheduledAt($now)
        ->save();

    $messages = [];
    set_error_handler(function (int $errno, string $errstr) use (&$messages): bool {
        $messages[] = $errstr;
        return true;
    });
    try {
        Mage::getModel('cron/observer')->dispatch(null);
    } finally {
        restore_error_handler();
    }

    $onBool = array_filter($messages, fn(string $m): bool => str_contains($m, 'property on bool'));
    expect($onBool)->toBe([]);

    $schedule = Mage::getModel('cron/schedule')->getCollection()
        ->addFieldToFilter('job_code', CRON_UNKNOWN_JOB_CODE)
        ->getFirstItem();

    expect($schedule->getStatus())->toBe(Mage_Cron_Model_Schedule::STATUS_PENDING);
});
