<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Boolean extends \Maho\Data\Form\Element\Select
{
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setValues([
            [
                'label' => Mage::helper('catalog')->__('No'),
                'value' => 0,
            ],
            [
                'label' => Mage::helper('catalog')->__('Yes'),
                'value' => 1,
            ],
        ]);
    }
}
