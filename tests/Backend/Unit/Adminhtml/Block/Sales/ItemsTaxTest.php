<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('adds tax in the same currency as each item price', function (array $taxData, array $unitPrices, array $subtotals): void {
    $block = new class extends Mage_Adminhtml_Block_Sales_Items_Abstract {
        #[\Override]
        public function displayPrices($basePrice, $price, $strong = false, $separator = '<br>'): string
        {
            return json_encode([$basePrice, $price], JSON_THROW_ON_ERROR);
        }
    };
    $block->setOrder(Mage::getModel('sales/order')->setStoreId(1));
    $item = new Maho\DataObject([
        'qty_ordered' => 2,
        'base_price' => 100.0,
        'price' => 80.0,
        'base_row_total' => 200.0,
        'row_total' => 160.0,
        ...$taxData,
    ]);

    expect(json_decode($block->displayPriceInclTax($item), true))->toBe($unitPrices)
        ->and(json_decode($block->displaySubtotalInclTax($item), true))->toBe($subtotals);
})->with([
    'tax before discount' => [
        ['base_tax_before_discount' => 50.0, 'tax_before_discount' => 40.0, 'base_tax_amount' => 40.0, 'tax_amount' => 32.0],
        [125, 100],
        [250, 200],
    ],
    'tax amount fallback' => [
        ['base_tax_amount' => 40.0, 'tax_amount' => 32.0],
        [120, 96],
        [240, 192],
    ],
]);
