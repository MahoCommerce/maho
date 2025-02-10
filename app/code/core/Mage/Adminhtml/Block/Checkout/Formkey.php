<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Mage_Adminhtml_Block_Checkout_Formkey
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Checkout_Formkey extends Mage_Adminhtml_Block_Template
{
    /**
     * Check form key validation on checkout.
     * If disabled, show notice.
     *
     * @return bool
     */
    public function canShow()
    {
        return !Mage::helper('core')->isFormKeyEnabled();
    }

    /**
     * Get url for edit Advanced -> Admin section
     *
     * @return string
     * @deprecated
     */
    public function getSecurityAdminUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit/section/admin');
    }

    /**
     * @return string
     */
    public function getEnableCSRFUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit/section/system');
    }
}
