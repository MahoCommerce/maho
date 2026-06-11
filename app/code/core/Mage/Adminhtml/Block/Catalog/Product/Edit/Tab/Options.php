<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Options extends Mage_Adminhtml_Block_Widget
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/product/edit/options.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'add_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label' => Mage::helper('catalog')->__('Add New Option'),
                    'class' => 'add',
                    'id'    => 'add_new_defined_option',
                ]),
        );

        $this->setChild(
            'options_box',
            $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_options_option'),
        );

        return parent::_prepareLayout();
    }

    public function getAddButtonHtml()
    {
        return $this->getChildHtml('add_button');
    }

    public function getOptionsBoxHtml()
    {
        return $this->getChildHtml('options_box');
    }
}
