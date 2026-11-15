<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function datetimeFrontendValue(?string $value): string
{
    $attribute = Mage::getModel('eav/entity_attribute')->setData([
        'attribute_code' => 'review_date',
        'frontend_input' => 'date',
    ]);
    $frontend = new Mage_Eav_Model_Entity_Attribute_Frontend_Datetime();
    $frontend->setAttribute($attribute);
    return $frontend->getValue(new Maho\DataObject(['review_date' => $value]));
}

it('shows a date value in the medium format of the locale', function (): void {
    expect(datetimeFrontendValue('2026-12-01 00:00:00'))->toBe('Dec 1, 2026');
});

it('keeps the calendar day of a date value', function (): void {
    expect(datetimeFrontendValue('2026-01-01'))->toBe('Jan 1, 2026');
});

it('shows nothing for an empty date value', function (?string $value): void {
    expect(datetimeFrontendValue($value))->toBe('');
})->with([null, '']);
