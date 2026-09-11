<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

$productName = 'Tee "quoted" </script><script>alert(1)</script>';
$originalConfig = [];

beforeEach(function () use (&$originalConfig): void {
    $store = Mage::app()->getStore();
    foreach ([Mage_Catalog_Helper_Data::XML_PATH_MSRP_ENABLED, Mage_Catalog_Helper_Data::XML_PATH_MSRP_APPLY_TO_ALL] as $path) {
        $originalConfig[$path] = $store->getConfig($path);
        $store->setConfig($path, '1');
    }
});

afterEach(function () use (&$originalConfig): void {
    $store = Mage::app()->getStore();
    foreach ($originalConfig as $path => $value) {
        $store->setConfig($path, $value);
    }
});

$msrpProduct = (static fn(string $name): Mage_Catalog_Model_Product => Mage::getModel('catalog/product')
    ->setId(1)
    ->setTypeId('simple')
    ->setStoreId(Mage::app()->getStore()->getId())
    ->setName($name)
    ->setPrice(10)
    ->setMsrp(20)
    ->setMsrpEnabled(Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Enabled::MSRP_ENABLE_YES)
    ->setMsrpDisplayActualPriceType(Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type::TYPE_ON_GESTURE)
    ->setIsSalable(false));

$render = (static fn(string $template, Mage_Catalog_Model_Product $product): string => Mage::app()->getLayout()->createBlock('catalog/product_price')
    ->setTemplate($template)
    ->setProduct($product)
    ->toHtml());

it('encodes the product name as a json string in the wishlist msrp script', function () use ($msrpProduct, $render, $productName) {
    $html = $render('wishlist/render/item/price_msrp_item.phtml', $msrpProduct($productName));

    expect($html)->toContain('Catalog.Map.addHelpLink')
        ->and($html)->toContain(Mage::helper('core')->jsonEncode($productName))
        ->and($html)->not->toContain('</script><script>alert(1)');
});

it('encodes the product name as a json string in the catalog msrp item script', function () use ($msrpProduct, $render, $productName) {
    $html = $render('catalog/product/price_msrp_item.phtml', $msrpProduct($productName));

    expect($html)->toContain('Catalog.Map.addHelpLink')
        ->and($html)->toContain(Mage::helper('core')->jsonEncode($productName))
        ->and($html)->not->toContain('</script><script>alert(1)');
});

it('encodes the product name as a json string in the catalog msrp noform script', function () use ($msrpProduct, $render, $productName) {
    $html = $render('catalog/product/price_msrp_noform.phtml', $msrpProduct($productName));

    expect($html)->toContain('Catalog.Map.addHelpLink')
        ->and($html)->toContain(Mage::helper('core')->jsonEncode($productName))
        ->and($html)->not->toContain('</script><script>alert(1)');
});
