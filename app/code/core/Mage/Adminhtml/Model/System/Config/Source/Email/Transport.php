<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Email_Transport
{
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => Mage::helper('adminhtml')->__('Disable All Email Communications')],
            ['value' => 'sendmail', 'label' => 'Sendmail'],
            ['value' => 'smtp', 'label' => 'SMTP'],
        ];
    }
}
