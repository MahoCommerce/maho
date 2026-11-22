<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

afterEach(function () {
    Mage::unregister('current_order');
});

describe('Mage_Tax_Helper_Data::getAllWeee()', function () {
    test('reads the order of the page when the caller passes no source', function () {
        $item = Mage::getModel('sales/order_item');
        Mage::helper('weee')->setApplied($item, [[
            'title' => 'Recycling fee',
            'row_amount' => 2.5,
            'row_amount_incl_tax' => 2.5,
        ]]);
        Mage::register('current_order', Mage::getModel('sales/order')->addItem($item));

        expect(Mage::helper('tax')->getAllWeee())->toBe(['Recycling fee' => 2.5]);
    });

    test('returns no fee when the caller passes no source and the page has no order', function () {
        expect(Mage::helper('tax')->getAllWeee())->toBe([]);
    });
});
