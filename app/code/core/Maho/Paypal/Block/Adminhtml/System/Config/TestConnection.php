<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Block_Adminhtml_System_Config_TestConnection extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\DataObject $element): string
    {
        $this->setTemplate('maho/paypal/system/config/test-connection.phtml');
        $this->setData('element', $element);
        $this->setData('ajax_url', Mage::helper('adminhtml')->getUrl('adminhtml/paypal_config/testConnection'));
        return $this->_toHtml();
    }
}
