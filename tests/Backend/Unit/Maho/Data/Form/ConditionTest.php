<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Data\Form\Condition;

describe('\Maho\Data\Form\Condition::evaluate()', function () {
    it('is true for an empty condition', function () {
        expect(Condition::evaluate([], []))->toBeTrue();
    });

    it('is true when the field holds one of the wanted values', function () {
        $condition = ['country_id' => ['IT', 'SM']];

        expect(Condition::evaluate($condition, ['country_id' => 'SM']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['country_id' => 'US']))->toBeFalse()
            ->and(Condition::evaluate($condition, []))->toBeFalse();
    });

    it('compares the values as strings', function () {
        expect(Condition::evaluate(['is_brand' => '1'], ['is_brand' => 1]))->toBeTrue()
            ->and(Condition::evaluate(['is_brand' => ['1']], ['is_brand' => true]))->toBeTrue();
    });

    it('takes false as no value, as the EAV forms do for a field that the request does not send', function () {
        expect(Condition::evaluate(['is_brand' => ['0']], ['is_brand' => false]))->toBeFalse()
            ->and(Condition::evaluate(['is_brand' => ['0']], ['is_brand' => '0']))->toBeTrue();
    });

    it('reads a multiselect value as a list or as a comma string', function () {
        $condition = ['topics' => ['7']];

        expect(Condition::evaluate($condition, ['topics' => ['3', '7']]))->toBeTrue()
            ->and(Condition::evaluate($condition, ['topics' => '3,7']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['topics' => '3,8']))->toBeFalse();
    });

    it('matches any value that is not empty with ANY_VALUE', function () {
        $condition = ['company' => [Condition::ANY_VALUE]];

        expect(Condition::evaluate($condition, ['company' => 'Maho Srl']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['company' => '0']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['company' => '  ']))->toBeFalse()
            ->and(Condition::evaluate($condition, ['company' => '']))->toBeFalse()
            ->and(Condition::evaluate($condition, ['company' => null]))->toBeFalse()
            ->and(Condition::evaluate($condition, []))->toBeFalse()
            ->and(Condition::evaluate(['topics' => [Condition::ANY_VALUE]], ['topics' => ['', '7']]))->toBeTrue();
    });

    it('shows a field when another field is empty, with ANY_VALUE in a NOT group', function () {
        $condition = [['operator' => 'NOT', 'condition' => ['sdi_code' => [Condition::ANY_VALUE]]]];

        expect(Condition::evaluate($condition, ['sdi_code' => '']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['sdi_code' => 'ABC1234']))->toBeFalse();
    });

    it('joins the entries of one level with AND', function () {
        $condition = ['customer_type' => ['company'], 'country_id' => ['IT']];

        expect(Condition::evaluate($condition, ['customer_type' => 'company', 'country_id' => 'IT']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['customer_type' => 'company', 'country_id' => 'US']))->toBeFalse();
    });

    it('applies the operator of each group', function (string $operator, array $values, bool $expected) {
        $condition = [['operator' => $operator, 'condition' => ['a' => ['1'], 'b' => ['1']]]];

        expect(Condition::evaluate($condition, $values))->toBe($expected);
    })->with([
        'AND, both' => ['AND', ['a' => '1', 'b' => '1'], true],
        'AND, one' => ['AND', ['a' => '1', 'b' => '0'], false],
        'OR, one' => ['OR', ['a' => '0', 'b' => '1'], true],
        'OR, none' => ['OR', ['a' => '0', 'b' => '0'], false],
        'NOT, none' => ['NOT', ['a' => '0', 'b' => '0'], true],
        'NOT, one' => ['NOT', ['a' => '1', 'b' => '0'], false],
        'XOR, one' => ['XOR', ['a' => '1', 'b' => '0'], true],
        'XOR, both' => ['XOR', ['a' => '1', 'b' => '1'], false],
    ]);

    it('evaluates a group inside a group', function () {
        // a = 1 OR (b = 1 AND c = 1)
        $condition = [['operator' => 'OR', 'condition' => [
            'a' => ['1'],
            ['operator' => 'AND', 'condition' => ['b' => ['1'], 'c' => ['1']]],
        ]]];

        expect(Condition::evaluate($condition, ['a' => '1']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['b' => '1', 'c' => '1']))->toBeTrue()
            ->and(Condition::evaluate($condition, ['b' => '1', 'c' => '0']))->toBeFalse();
    });
});

describe('\Maho\Data\Form\Condition::fields() and wantedValues()', function () {
    it('returns the fields and the values of every level', function () {
        $condition = [['operator' => 'OR', 'condition' => [
            'a' => ['1'],
            ['operator' => 'AND', 'condition' => ['b' => '2', 'a' => ['3']]],
        ]]];

        expect(Condition::fields($condition))->toBe(['a', 'b'])
            ->and(Condition::wantedValues($condition))->toBe(['a' => ['1', '3'], 'b' => ['2']]);
    });
});

describe('\Maho\Data\Form\Condition::structureErrors()', function () {
    it('accepts a valid condition', function () {
        expect(Condition::structureErrors([['operator' => 'XOR', 'condition' => ['a' => ['1'], 'b' => '2']]]))->toBe([]);
    });

    it('reports an unknown operator, an empty group, an entry that is not a group and a field with no values', function () {
        expect(Condition::structureErrors([['operator' => 'NAND', 'condition' => ['a' => ['1']]]]))->toHaveCount(1)
            ->and(Condition::structureErrors([['operator' => 'AND', 'condition' => []]]))->toHaveCount(1)
            ->and(Condition::structureErrors(['x']))->toHaveCount(1)
            ->and(Condition::structureErrors(['a' => []]))->toHaveCount(1)
            ->and(Condition::structureErrors(['a' => [['nested']]]))->toHaveCount(1);
    });
});
