<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const MULTISELECT_OPTIONS = ['red' => 1, 'blue' => 2, 'black | white' => 3];

it('splits a pipe separated cell into option ids', function () {
    expect(Mage_ImportExport_Model_Import_Entity_Abstract::multiselectOptionIds(MULTISELECT_OPTIONS, 'Red| blue '))
        ->toBe([1, 2]);
});

it('keeps a label that itself contains the separator as one value', function () {
    expect(Mage_ImportExport_Model_Import_Entity_Abstract::multiselectOptionIds(MULTISELECT_OPTIONS, 'Black | White'))
        ->toBe([3]);
});

it('rejects a cell with one unknown value', function () {
    expect(Mage_ImportExport_Model_Import_Entity_Abstract::multiselectOptionIds(MULTISELECT_OPTIONS, 'red|green'))
        ->toBeNull();
});
