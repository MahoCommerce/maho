<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Email_Smtpsecurity
{
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => Mage::helper('adminhtml')->__('None')],
            ['value' => 'ssl', 'label' => 'SSL'],
            ['value' => 'tls', 'label' => 'TLS'],
        ];
    }
}
