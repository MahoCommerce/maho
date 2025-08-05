<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Log_Level
{
    public function toOptionArray()
    {
        $helper = Mage::helper('adminhtml');

        return [
            Mage::LOG_EMERGENCY => $helper->__('Emergency'),
            Mage::LOG_ALERT     => $helper->__('Alert'),
            Mage::LOG_CRITICAL  => $helper->__('Critical'),
            Mage::LOG_ERROR     => $helper->__('Error'),
            Mage::LOG_WARNING   => $helper->__('Warning'),
            Mage::LOG_NOTICE    => $helper->__('Notice'),
            Mage::LOG_INFO      => $helper->__('Informational'),
            Mage::LOG_DEBUG     => $helper->__('Debug'),
        ];
    }
}
