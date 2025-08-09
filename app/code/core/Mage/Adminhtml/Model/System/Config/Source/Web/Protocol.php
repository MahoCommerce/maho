<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Web_Protocol
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => ''],
            ['value' => 'http', 'label' => Mage::helper('adminhtml')->__('HTTP (unsecure)')],
            ['value' => 'https', 'label' => Mage::helper('adminhtml')->__('HTTPS (SSL)')],
        ];
    }
}
