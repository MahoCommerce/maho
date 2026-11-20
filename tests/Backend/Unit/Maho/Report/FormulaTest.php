<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Report\Formula;

$values = static fn(array $map): Closure => static fn(string $code): ?float => $map[$code] ?? null;

it('respects operator precedence and parentheses', function () use ($values) {
    $formula = Formula::parse('gross - discounts * 2 + (returns - 1) / 2');
    expect($formula->evaluate($values(['gross' => 100.0, 'discounts' => 10.0, 'returns' => 5.0])))->toBe(82.0);
});

it('lists every metric that a formula reads once', function () {
    expect(Formula::parse('a / (a + b) - -c')->references())->toBe(['a', 'b', 'c']);
});

it('gives null for a division by zero and for a null operand', function () use ($values) {
    $formula = Formula::parse('net_sales / orders');
    expect($formula->evaluate($values(['net_sales' => 10.0, 'orders' => 0.0])))->toBeNull()
        ->and($formula->evaluate($values(['orders' => 2.0])))->toBeNull();
});

it('renders a division that is decimal and safe from zero on every engine', function () {
    $sql = Formula::parse('a / b')->toSql(static fn(string $code): string => "SUM({$code})");
    expect($sql)->toBe('((SUM(a)) * 1.0 / NULLIF((SUM(b)), 0))');
});

it('rejects a formula with a syntax error', function (string $formula) {
    expect(fn() => Formula::parse($formula))->toThrow(InvalidArgumentException::class);
})->with(['a +', '(a + b', 'a $ b', '', 'a b']);
