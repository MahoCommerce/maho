<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Convert_Profile_Edit_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $values = [
            'create' => Mage::helper('adminhtml')->__('Create'),
            'run'    => Mage::helper('adminhtml')->__('Run'),
            'update' => Mage::helper('adminhtml')->__('Update'),
        ];
        $value = $row->getData($this->getColumn()->getIndex());
        return $values[$value];
    }
}
