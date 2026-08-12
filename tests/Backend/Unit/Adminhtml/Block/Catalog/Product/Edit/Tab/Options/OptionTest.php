<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Custom option price precision in the admin form', function () {
    // Regression for #1262: getPriceValue() rounded to 2 decimals, so a stored
    // 10.6557 displayed as 10.66 and the next save wrote 10.66 back.

    it('renders fixed prices with the full stored 4-decimal precision', function () {
        $block = new Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Options_Option();

        expect($block->getPriceValue('10.6557', 'fixed'))->toBe('10.6557');
        expect($block->getPriceValue('10.6600', 'fixed'))->toBe('10.66');
        expect($block->getPriceValue('10.5', 'fixed'))->toBe('10.50');
    });

    it('renders percent prices with the full stored 4-decimal precision', function () {
        $block = new Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Options_Option();

        expect($block->getPriceValue('12.3456', 'percent'))->toBe('12.3456');
        expect($block->getPriceValue('12.3400', 'percent'))->toBe('12.34');
    });
});
