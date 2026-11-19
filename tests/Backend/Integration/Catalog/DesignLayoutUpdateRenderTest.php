<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * GHSA-8m3h-7cgh-jf96: the save validates the custom layout update of a product, but an update that was saved
 * before a validator rule existed must not run when the product page renders.
 */
function catalogDesignLayoutUpdates(string $layoutUpdateXml): array
{
    $product = Mage::getModel('catalog/product')->setData('custom_layout_update', $layoutUpdateXml);

    return Mage::getModel('catalog/design')->getDesignSettings($product)->getLayoutUpdates();
}

it('does not apply a stored layout update that the validator refuses', function (string $xml) {
    expect(catalogDesignLayoutUpdates($xml))->not->toContain($xml);
})->with([
    'admin grid' => ['<reference name="content"><block type="adminhtml/sales_order_grid" name="g"/></reference>'],
    'helper argument' => ['<reference name="content"><action method="setText"><text helper="core/string/truncate"/></action></reference>'],
    'invalid XML' => ['<reference name="content">'],
]);

it('applies a stored layout update that the validator accepts', function () {
    $xml = '<reference name="content"><block type="core/text" name="layout.update.text"/></reference>';

    expect(catalogDesignLayoutUpdates($xml))->toBe([$xml]);
});
