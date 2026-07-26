<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Unit coverage for optional multi-select layered navigation (issue #1151).
 *
 * These exercise the value-level logic that turns a facet from single-value into
 * OR-within-facet: request parsing (both URL forms), and the append/subtract math
 * a filter item uses to build its add/remove URLs. Single-select behaviour must
 * stay byte-for-byte unchanged, so it is asserted alongside.
 */

/**
 * Minimal filter double: the item URL helpers only read isMultipleSelect(),
 * getAppliedValues() and getResetValue() off their filter.
 */
function multiSelectFilterStub(bool $multiple, array $appliedValues): Maho\DataObject
{
    return new class ($multiple, $appliedValues) extends Maho\DataObject {
        public function __construct(private bool $multiple, private array $appliedValues)
        {
            parent::__construct();
        }

        public function isMultipleSelect(): bool
        {
            return $this->multiple;
        }

        public function getAppliedValues(): array
        {
            return $this->appliedValues;
        }

        public function getResetValue()
        {
            return null;
        }
    };
}

function invokeProtected(object $object, string $method): mixed
{
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);
    return $ref->invoke($object);
}

describe('request value parsing', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('catalog/layer_filter_attribute');
        $this->parse = new ReflectionMethod($this->filter, '_parseRequestValues');
        $this->parse->setAccessible(true);
    });

    test('the comma-separated form is split into values', function () {
        expect($this->parse->invoke($this->filter, '12,15'))->toBe(['12', '15']);
    });

    test('the repeated-parameter (array) form is accepted as-is', function () {
        expect($this->parse->invoke($this->filter, ['12', '15']))->toBe(['12', '15']);
    });

    test('values are trimmed, empties dropped and duplicates removed', function () {
        expect($this->parse->invoke($this->filter, ' 12 , 15 ,,12'))->toBe(['12', '15']);
    });

    test('empty and null request values yield no filter values', function () {
        expect($this->parse->invoke($this->filter, ''))->toBe([]);
        expect($this->parse->invoke($this->filter, null))->toBe([]);
    });
});

describe('multi-select item URL values', function () {
    test('an option appends its value to the current selection', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(true, ['12']))
            ->setValue('15');

        expect(invokeProtected($item, '_getAppliedValue'))->toBe('12,15');
    });

    test('the first selected option produces a single value, no comma', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(true, []))
            ->setValue('15');

        expect(invokeProtected($item, '_getAppliedValue'))->toBe('15');
    });

    test('an already-selected value is not duplicated when re-applied', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(true, ['12', '15']))
            ->setValue('12');

        expect(invokeProtected($item, '_getAppliedValue'))->toBe('12,15');
    });

    test('removing one value keeps the others selected', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(true, ['12', '15']))
            ->setValue('12');

        expect(invokeProtected($item, '_getRemovedValue'))->toBe('15');
    });

    test('removing the last value resets the facet', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(true, ['12']))
            ->setValue('12');

        expect(invokeProtected($item, '_getRemovedValue'))->toBeNull();
    });
});

describe('single-select behaviour is unchanged', function () {
    test('an option keeps replacing rather than appending', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(false, []))
            ->setValue('15');

        expect(invokeProtected($item, '_getAppliedValue'))->toBe('15');
    });

    test('removing an item resets the facet', function () {
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(multiSelectFilterStub(false, []))
            ->setValue('15');

        expect(invokeProtected($item, '_getRemovedValue'))->toBeNull();
    });
});

describe('filter defaults', function () {
    test('attribute filters are single-select unless the attribute opts in', function () {
        $filter = Mage::getModel('catalog/layer_filter_attribute');
        expect($filter->getAppliedValues())->toBe([]);
    });
});
