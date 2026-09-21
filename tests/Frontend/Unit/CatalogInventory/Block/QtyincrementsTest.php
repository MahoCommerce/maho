<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

afterEach(function (): void {
    Mage::unregister('current_product');
});

it('escapes the product name in the qty increments notice', function () {
    $product = Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('simple')
        ->setName('Tee <img src=x onerror=alert(1)>');
    Mage::register('current_product', $product);

    $block = new class extends Mage_CatalogInventory_Block_Qtyincrements {
        #[\Override]
        public function getProductQtyIncrements()
        {
            return 5.0;
        }
    };
    $block->setLayout(Mage::app()->getLayout());
    $block->setTemplate('cataloginventory/qtyincrements.phtml');

    $html = $block->toHtml();

    expect($html)->toContain('increments of 5')
        ->and($html)->not->toContain('<img')
        ->and($html)->toContain('Tee &lt;img src=x onerror=alert(1)&gt;');
});
