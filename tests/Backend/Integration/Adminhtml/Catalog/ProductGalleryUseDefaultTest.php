<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin product page posts use_default[] to Mage_Catalog_Service_Product::useDefault(), which accepts only the
 * codes of store view attributes (#1852). The gallery of a store view must not post the image label or position
 * there: the gallery JSON holds them.
 */

it('posts only store view attributes in use_default from the gallery of a store view', function () {
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $productId = (int) Mage::getResourceModel('catalog/product_collection')->getFirstItem()->getId();
    $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);

    $form = new \Maho\Data\Form();
    $form->setDataObject($product);
    $form->addType('gallery', Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Gallery::class);
    $element = $form->addField('media_gallery', 'gallery', ['name' => 'media_gallery']);
    $element->setValue($product->getMediaGallery());

    preg_match_all('/<input\b[^>]*\bname="use_default\[\]"[^>]*>/', $element->getElementHtml(), $inputs);
    $codes = array_map(
        fn(string $input): string => preg_match('/\bvalue="([^"]*)"/', $input, $value) ? $value[1] : '',
        $inputs[0],
    );

    expect($codes)->not->toBeEmpty()
        ->and($codes)->not->toContain('label')
        ->and($codes)->not->toContain('position')
        ->and(Mage::helper('catalog')->getStoreScopeAttributes(Mage_Catalog_Model_Product::ENTITY, $codes, $storeId))
        ->toHaveCount(count($codes));
});
