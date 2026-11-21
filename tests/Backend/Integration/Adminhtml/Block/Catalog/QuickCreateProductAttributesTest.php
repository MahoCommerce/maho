<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the shared product attribute codes when preparing the quick create form', function (): void {
    $eav = Mage::getSingleton('eav/config');
    $attributeSetId = (int) $eav->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getDefaultAttributeSetId();
    $sku = $eav->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'sku');
    $product = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        ->setAttributeSetId($attributeSetId);
    Mage::register('current_product', $product);

    $layout = Mage::app()->getLayout();
    $layout->createBlock('page/html_head', 'head');
    $block = $layout->createBlock('adminhtml/catalog_product_edit_tab_super_config_simple');
    $block->toHtml();

    $element = $block->getForm()->getElement('simple_product_sku');
    expect($element->getEntityAttribute()->getAttributeCode())->toBe('simple_product_sku')
        ->and($sku->getAttributeCode())->toBe('sku');
});
