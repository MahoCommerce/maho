<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Customer_OnlineController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/online';

    #[Maho\Config\Route('/admin/customer_online/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Customers'))->_title($this->__('Online Customers'));

        if ($this->getRequest()->getParam('ajax')) {
            $this->_forward('grid');
            return;
        }

        $this->loadLayout();

        $this->_setActiveMenu('customer/online');

        $this->_addContent($this->getLayout()->createBlock('adminhtml/customer_online', 'customers'));

        $this->_addBreadcrumb(Mage::helper('customer')->__('Customers'), Mage::helper('customer')->__('Customers'));
        $this->_addBreadcrumb(Mage::helper('customer')->__('Online Customers'), Mage::helper('customer')->__('Online Customers'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/customer_online/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/customer_online_grid', 'customer.grid')->toHtml(),
        );
    }
}
