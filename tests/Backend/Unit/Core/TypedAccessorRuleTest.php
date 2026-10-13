<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('splits the comma-separated customer groups of a rule into a list', function (string $alias) {
    $rule = Mage::getModel($alias)->setData('customer_group_ids', '1,2');

    expect($rule->getCustomerGroupIds())->toBe(['1', '2']);
})->with(['salesrule/rule', 'catalogrule/rule']);

it('returns the discount amount of a rule as a float', function (string $alias) {
    $rule = Mage::getModel($alias)->setData('discount_amount', '10.5000');

    expect($rule->getDiscountAmount())->toBe(10.5);
})->with(['salesrule/rule', 'catalogrule/rule']);
