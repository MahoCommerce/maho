<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage_Catalog_Model_Resource_Eav_Attribute as CatalogAttribute;

uses(Tests\MahoBackendTestCase::class);

/**
 * Build the element graph the block reads through:
 * block->getElement()->getEntityAttribute() and ->getForm()->getDataObject().
 */
function makeFieldsetElementBlock(int $isGlobal, int $storeId): Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element
{
    $attribute = Mage::getModel('catalog/resource_eav_attribute');
    $attribute->setData('is_global', $isGlobal);

    $dataObject = Mage::getModel('catalog/product');
    $dataObject->setStoreId($storeId);

    $form = new Maho\DataObject(['data_object' => $dataObject]);
    $element = new Maho\DataObject(['entity_attribute' => $attribute, 'form' => $form]);

    /** @var Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element $block */
    $block = Mage::app()->getLayout()->createBlock('adminhtml/catalog_form_renderer_fieldset_element');

    // _element is a protected property with no public setter (it is normally
    // assigned inside render()); inject the stub graph directly.
    $property = new ReflectionProperty(Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element::class, '_element');
    $property->setValue($block, $element);

    return $block;
}

describe('Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element::isGlobalAttributeEditedInStoreScope', function () {
    it('is true for a global attribute edited on a store view scope', function () {
        $block = makeFieldsetElementBlock(CatalogAttribute::SCOPE_GLOBAL, 1);

        expect($block->isGlobalAttributeEditedInStoreScope())->toBeTrue();
    });

    it('is false for a global attribute edited on the default scope', function () {
        $block = makeFieldsetElementBlock(CatalogAttribute::SCOPE_GLOBAL, 0);

        expect($block->isGlobalAttributeEditedInStoreScope())->toBeFalse();
    });

    it('is false for a website scoped attribute on a store view scope', function () {
        $block = makeFieldsetElementBlock(CatalogAttribute::SCOPE_WEBSITE, 1);

        expect($block->isGlobalAttributeEditedInStoreScope())->toBeFalse();
    });

    it('is false for a store scoped attribute on a store view scope', function () {
        $block = makeFieldsetElementBlock(CatalogAttribute::SCOPE_STORE, 1);

        expect($block->isGlobalAttributeEditedInStoreScope())->toBeFalse();
    });
});
