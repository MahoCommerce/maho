<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Design_Darkmode
{
    public function toOptionArray(): array
    {
        $helper = Mage::helper('adminhtml');
        return [
            ['value' => 1, 'label' => $helper->__('Follow Device')],
            ['value' => 0, 'label' => $helper->__('Off')],
        ];
    }
}
