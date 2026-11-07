<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    Mage::app()->getStore()->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 'Europe/London');
});

it('writes a UTC time of the answer in store time', function (string $text, string $expected): void {
    expect(Maho_Ai_Model_Chat_StoreTime::toStore($text, null))->toBe($expected);
})->with([
    'summer time' => ['Placed on 2026-10-19 06:21:10 UTC.', 'Placed on 2026-10-19 07:21.'],
    'winter time' => ['Placed on 2026-10-26 06:21 UTC.', 'Placed on 2026-10-26 06:21.'],
    'the next day in the store' => ['| 2026-10-18 23:30 UTC |', '| 2026-10-19 00:30 |'],
    'a time without UTC' => ['Placed at 2026-10-19 06:21.', 'Placed at 2026-10-19 06:21.'],
]);
