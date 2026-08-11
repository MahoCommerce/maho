<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function gaTrackingSimpleWithSkuOption(): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setSku('basesku123');
    gaTrackingAddSkuOption($product);
    return $product;
}

function gaTrackingAddSkuOption(Mage_Catalog_Model_Product $product): void
{
    $option = Mage::getModel('catalog/product_option')
        ->setId(7)
        ->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD)
        ->setSku('mycustom option');
    $product->addOption($option);
    $product->addCustomOption('option_ids', '7');
    $product->addCustomOption('option_7', 'engraved text');
}

describe('getTrackingSku', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('googleanalytics');
    });

    it('returns the raw base SKU when custom options make getSku() composite', function () {
        $product = gaTrackingSimpleWithSkuOption();

        expect($product->getSku())->toBe('basesku123-mycustom option');
        expect($this->helper->getTrackingSku($product))->toBe('basesku123');
    });

    it('returns the variant SKU for configurables, without any custom-option suffix', function () {
        $simple = Mage::getModel('catalog/product')->setId(11)->setSku('TSHIRT-M');
        $configurable = Mage::getModel('catalog/product')
            ->setTypeId(Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
            ->setSku('TSHIRT');
        $configurable->addCustomOption('simple_product', 11, $simple);
        gaTrackingAddSkuOption($configurable);

        expect($configurable->getSku())->toBe('TSHIRT-M-mycustom option');
        expect($this->helper->getTrackingSku($configurable))->toBe('TSHIRT-M');
    });

    it('returns getSku() for products without custom options', function () {
        $product = Mage::getModel('catalog/product')->setSku('plain-sku');

        expect($this->helper->getTrackingSku($product))->toBe('plain-sku');
    });

    it('returns null when there is no product', function () {
        expect($this->helper->getTrackingSku(null))->toBeNull();
    });
});
