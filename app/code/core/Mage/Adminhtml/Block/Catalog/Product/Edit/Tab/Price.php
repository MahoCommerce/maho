<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Price extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $product = Mage::registry('product');

        $form = new \Maho\Data\Form();
        $fieldset = $form->addFieldset('tiered_price', ['legend' => Mage::helper('catalog')->__('Tier Pricing')]);

        $fieldset->addField('default_price', 'label', [
            'label' => Mage::helper('catalog')->__('Default Price'),
            'title' => Mage::helper('catalog')->__('Default Price'),
            'name' => 'default_price',
            'bold' => true,
            'value' => $product->getPrice(),
        ]);

        $fieldset->addField('tier_price', 'text', [
            'name' => 'tier_price',
            'class' => 'requried-entry',
            'value' => $product->getData('tier_price'),
        ]);

        $renderer = $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_price_tier');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            $form->getElement('tier_price')->setRenderer($renderer);
        }

        $this->setForm($form);
        return $this;
    }
}
