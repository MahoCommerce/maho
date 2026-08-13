<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Gift card balance precision in the admin form', function () {
    // The balance columns are decimal(12,4). Rendering them at 2dp made an
    // untouched field post back a rounded value, which overwrote the balance
    // and logged a false adjustment.

    it('renders the full stored precision', function () {
        $helper = Mage::helper('adminhtml');

        expect($helper->formatPriceForInput('12.3456'))->toBe('12.3456');
        expect($helper->formatPriceForInput('30.0000'))->toBe('30.00');
        expect($helper->formatPriceForInput('30.5'))->toBe('30.50');
    });

    it('round-trips an untouched balance without an adjustment', function () {
        $stored = '12.3456';
        $posted = Mage::helper('adminhtml')->formatPriceForInput($stored);

        expect((float) $posted)->toBe((float) $stored);
    });
});
