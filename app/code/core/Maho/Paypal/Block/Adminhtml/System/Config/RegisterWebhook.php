<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Adminhtml_System_Config_RegisterWebhook extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\DataObject $element): string
    {
        $this->setTemplate('maho/paypal/system/config/register-webhook.phtml');
        $this->setData('element', $element);
        $this->setData('ajax_url', Mage::helper('adminhtml')->getUrl('adminhtml/paypal_config/registerWebhook'));
        return $this->_toHtml();
    }
}
