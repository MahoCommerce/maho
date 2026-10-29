<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

/**
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api2_Block_Adminhtml_Roles extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_blockGroup = 'api2';
        $this->_controller = 'adminhtml_roles';
        $this->_headerText = Mage::helper('adminhtml')->__('REST Roles');

        //check allow edit
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
<<<<<<< HEAD
        if ($session->isAllowed('system/api/roles/add')) {
            $this->_updateButton('add', 'label', $this->__('Add Admin Role'));
        } else {
=======
        if (!$session->isAllowed('system/api/rest_roles/add')) {
>>>>>>> 628960d8 (Fixed security issues from the OpenMage 20.19 advisories, such as unpaid orders with the free payment method and admin roles that reached actions outside their permissions (#1688))
            $this->_removeButton('add');
        }
    }
}
