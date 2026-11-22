<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('escapes the HTML of an order option value in the admin item name column', function (): void {
    $block = new Mage_Adminhtml_Block_Sales_Items_Column_Name();
    $value = '<img src=x onerror=alert(1)>' . str_repeat('a', 40) . '<script>alert(2)</script>';

    $formatted = $block->getFormattedOption($value);

    expect($formatted['value'] . $formatted['remainder'])->not->toContain('<img')
        ->and($formatted['value'] . $formatted['remainder'])->not->toContain('<script')
        ->and($formatted['value'])->toStartWith('&lt;img src=x onerror=alert(1)&gt;')
        ->and($formatted['remainder'])->toContain('&lt;script&gt;');
});

it('keeps the line breaks and does not encode an escaped value again', function (): void {
    $block = new Mage_Adminhtml_Block_Sales_Items_Column_Name();

    $formatted = $block->getFormattedOption("Black &amp; White\nLarge");

    expect($formatted['value'])->toBe("Black &amp; White<br />\nLarge")
        ->and($formatted['remainder'])->toBe('');
});
