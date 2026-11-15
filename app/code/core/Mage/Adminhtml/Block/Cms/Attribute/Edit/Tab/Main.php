<?php

/**
 * The properties tab of a CMS page attribute.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Cms_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Tab_Main
{
    #[\Override]
    protected function _prepareEntityFields(\Maho\Data\Form $form, \Maho\Data\Form\Element\Fieldset $fieldset): void
    {
        $helper = Mage::helper('cms');
        $yesno = Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray();

        $front = $form->addFieldset('front_fieldset', ['legend' => $helper->__('Frontend Properties')]);

        $front->addField('is_html_allowed_on_front', 'select', [
            'name' => 'is_html_allowed_on_front',
            'label' => $helper->__('Allow HTML Tags on Frontend'),
            'title' => $helper->__('Allow HTML Tags on Frontend'),
            'values' => $yesno,
        ]);

        $front->addField('is_visible_on_front', 'select', [
            'name' => 'is_visible_on_front',
            'label' => $helper->__('Visible on Frontend'),
            'title' => $helper->__('Visible on Frontend'),
            'note' => $helper->__('Show the value in the list of details below the content of the page'),
            'values' => $yesno,
        ]);
    }
}
