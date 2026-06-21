<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function feedManagerProductCondition(string $operator): Maho_FeedManager_Model_Rule_Condition_Product
{
    /** @var Maho_FeedManager_Model_Rule_Condition_Product $condition */
    $condition = Mage::getModel('feedmanager/rule_condition_product');
    $condition->setOperator($operator);
    return $condition;
}

describe('is_empty / is_not_empty operators', function () {
    test('operators are exposed in the default operator options', function () {
        $options = feedManagerProductCondition('is_empty')->getDefaultOperatorOptions();
        expect($options)->toHaveKey('is_empty')
            ->and($options)->toHaveKey('is_not_empty');
    });

    test('operators are added to every input type', function () {
        $condition = feedManagerProductCondition('is_empty');
        $condition->loadOperatorOptions();
        $map = $condition->getOperatorByInputType();
        expect($map)->toBeArray();
        foreach ($map as $operators) {
            expect($operators)->toContain('is_empty')
                ->and($operators)->toContain('is_not_empty');
        }
    });

    test('is_empty matches null, empty string and empty array', function () {
        $condition = feedManagerProductCondition('is_empty');
        expect($condition->validateAttribute(null))->toBeTrue()
            ->and($condition->validateAttribute(''))->toBeTrue()
            ->and($condition->validateAttribute([]))->toBeTrue();
    });

    test('is_empty does not match a non-empty value', function () {
        $condition = feedManagerProductCondition('is_empty');
        expect($condition->validateAttribute('value'))->toBeFalse()
            ->and($condition->validateAttribute(['x']))->toBeFalse();
    });

    test('is_empty treats numeric zero as NOT empty', function () {
        $condition = feedManagerProductCondition('is_empty');
        expect($condition->validateAttribute(0))->toBeFalse()
            ->and($condition->validateAttribute('0'))->toBeFalse();
    });

    test('is_not_empty is the inverse of is_empty', function () {
        $condition = feedManagerProductCondition('is_not_empty');
        expect($condition->validateAttribute(null))->toBeFalse()
            ->and($condition->validateAttribute(''))->toBeFalse()
            ->and($condition->validateAttribute([]))->toBeFalse()
            ->and($condition->validateAttribute('value'))->toBeTrue()
            ->and($condition->validateAttribute(0))->toBeTrue();
    });
});
