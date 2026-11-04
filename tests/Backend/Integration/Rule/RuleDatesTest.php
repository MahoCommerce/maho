<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('reads the From and To dates of a posted rule as Y-m-d strings', function (string $model): void {
    $rule = Mage::getModel($model);
    $rule->loadPost(['from_date' => '2026-11-01', 'to_date' => '2026-11-30']);

    expect($rule->getFromDate())->toBe('2026-11-01')
        ->and($rule->getToDate())->toBe('2026-11-30');
})->with(['salesrule/rule', 'catalogrule/rule']);

it('cuts the time of day from a posted date that is not in Y-m-d format', function (string $model): void {
    $rule = Mage::getModel($model);
    $rule->loadPost(['from_date' => '2026-11-01 10:30:00']);

    expect($rule->getFromDate())->toBe('2026-11-01');
})->with(['salesrule/rule', 'catalogrule/rule']);

it('saves a cart price rule that has a From and a To date', function (): void {
    $rule = Mage::getModel('salesrule/rule');
    $rule->loadPost([
        'name' => 'Rule dates test',
        'is_active' => 1,
        'website_ids' => [1],
        'customer_group_ids' => [0],
        'simple_action' => 'by_percent',
        'discount_amount' => 5,
        'from_date' => '2026-11-01',
        'to_date' => '2026-11-01',
    ]);
    $rule->save();

    try {
        $loaded = Mage::getModel('salesrule/rule')->load($rule->getId());
        expect($loaded->getFromDate())->toBe('2026-11-01')
            ->and($loaded->getToDate())->toBe('2026-11-01');
    } finally {
        $rule->delete();
    }
});

it('saves a cart price rule with an empty From and To date as no date', function (): void {
    $rule = Mage::getModel('salesrule/rule');
    $rule->loadPost([
        'name' => 'Rule empty dates test',
        'is_active' => 1,
        'website_ids' => [1],
        'customer_group_ids' => [0],
        'simple_action' => 'by_percent',
        'discount_amount' => 5,
        'from_date' => '',
        'to_date' => '',
    ]);
    $rule->save();

    try {
        $loaded = Mage::getModel('salesrule/rule')->load($rule->getId());
        expect($loaded->getFromDate())->toBeNull()
            ->and($loaded->getToDate())->toBeNull();
    } finally {
        $rule->delete();
    }
});
