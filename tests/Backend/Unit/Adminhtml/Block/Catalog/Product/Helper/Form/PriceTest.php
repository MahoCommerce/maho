<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Admin product price field precision', function () {
    // Regression for #1262: the field rendered number_format($value, 2), so a
    // stored 10.6557 displayed as 10.66 and the next save wrote 10.66 back.

    it('renders the full stored 4-decimal precision', function () {
        $element = new Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Price();
        $element->setValue('10.6557');

        expect($element->getEscapedValue())->toBe('10.6557');
    });

    it('trims trailing zeros down to two decimals', function () {
        $element = new Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Price();

        $element->setValue('10.6600');
        expect($element->getEscapedValue())->toBe('10.66');

        $element->setValue('10.6550');
        expect($element->getEscapedValue())->toBe('10.655');
    });

    it('keeps at least two decimals', function () {
        $element = new Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Price();

        $element->setValue('10.5');
        expect($element->getEscapedValue())->toBe('10.50');

        $element->setValue('10');
        expect($element->getEscapedValue())->toBe('10.00');
    });

    it('renders large values without a thousands separator', function () {
        $element = new Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Price();
        $element->setValue('1234.5678');

        expect($element->getEscapedValue())->toBe('1234.5678');
    });

    it('returns null for a non-numeric value', function () {
        $element = new Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Price();

        $element->setValue('');
        expect($element->getEscapedValue())->toBeNull();

        $element->setValue('abc');
        expect($element->getEscapedValue())->toBeNull();
    });
});
