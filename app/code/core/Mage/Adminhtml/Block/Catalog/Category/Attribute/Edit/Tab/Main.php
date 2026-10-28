<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * The properties tab of a category attribute.
 */
class Mage_Adminhtml_Block_Catalog_Category_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Tab_Main
{
    #[\Override]
    protected function _prepareEntityFields(\Maho\Data\Form $form, \Maho\Data\Form\Element\Fieldset $fieldset): void
    {
        $helper = Mage::helper('catalog');
        $yesno = Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray();

        $fieldset->addField('is_global', 'select', [
            'name' => 'is_global',
            'label' => $helper->__('Scope'),
            'title' => $helper->__('Scope'),
            'note' => $helper->__('Declare attribute value saving scope'),
            'values' => [
                Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE => $helper->__('Store View'),
                Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE => $helper->__('Website'),
                Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL => $helper->__('Global'),
            ],
        ], 'attribute_code');

        $front = $form->addFieldset('front_fieldset', ['legend' => $helper->__('Frontend Properties')]);

        $front->addField('is_wysiwyg_enabled', 'select', [
            'name' => 'is_wysiwyg_enabled',
            'label' => $helper->__('Enable WYSIWYG'),
            'title' => $helper->__('Enable WYSIWYG'),
            'values' => $yesno,
        ]);

        $front->addField('is_html_allowed_on_front', 'select', [
            'name' => 'is_html_allowed_on_front',
            'label' => $helper->__('Allow HTML Tags on Frontend'),
            'title' => $helper->__('Allow HTML Tags on Frontend'),
            'values' => $yesno,
        ]);

        $front->addField('is_visible_on_front', 'select', [
            'name' => 'is_visible_on_front',
            'label' => $helper->__('Visible on Category Page on Front-end'),
            'title' => $helper->__('Visible on Category Page on Front-end'),
            'values' => $yesno,
        ]);

        $front->addField('position', 'text', [
            'name' => 'position',
            'label' => $helper->__('Position'),
            'title' => $helper->__('Position'),
            'note' => $helper->__('Position of the attribute in the list on the category page'),
            'class' => 'validate-digits',
        ]);
    }
}
