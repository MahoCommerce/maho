<?php

/**
 * The properties tab of a blog post or blog category attribute.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Adminhtml_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Tab_Main
{
    #[\Override]
    protected function _prepareEntityFields(\Maho\Data\Form $form, \Maho\Data\Form\Element\Fieldset $fieldset): void
    {
        $helper = Mage::helper('blog');
        $yesno = Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray();

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
            'label' => $helper->__('Visible on Frontend'),
            'title' => $helper->__('Visible on Frontend'),
            'note' => $helper->__('Show the value in the list of details on the post or category page'),
            'values' => $yesno,
        ]);

        $front->addField('position', 'text', [
            'name' => 'position',
            'label' => $helper->__('Position'),
            'title' => $helper->__('Position'),
            'note' => $helper->__('Position of the attribute in the list of details'),
            'class' => 'validate-digits',
        ]);
    }
}
