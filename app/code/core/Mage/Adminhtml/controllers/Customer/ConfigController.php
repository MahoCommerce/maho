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
 * config controller
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Customer_ConfigController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/config';

    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('customer/config');
        $this->_addBreadcrumb(Mage::helper('customer')->__('Customer'), Mage::helper('customer')->__('Customer'));
        $this->_addBreadcrumb(Mage::helper('customer')->__('Config'), Mage::helper('customer')->__('Config'));
        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/customer_config'),
        );
        $this->getLayout()->getBlock('left')
            ->append($this->getLayout()->createBlock('adminhtml/customer_config_tabs'));

        $this->renderLayout();
    }
}
