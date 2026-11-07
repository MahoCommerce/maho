<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('starts the intervals of a year period at the year of the start date', function (): void {
    $intervals = Mage::helper('reports')->getIntervals('2018-03-05', '2020-01-01', Mage_Reports_Helper_Data::REPORT_PERIOD_TYPE_YEAR);

    expect($intervals)->toBe(['2018', '2019', '2020']);
});
