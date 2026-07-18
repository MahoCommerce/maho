<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

class Mage_Oauth_Block_Adminhtml_Oauth_Consumer extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Construct grid container
     */
    public function __construct()
    {
        parent::__construct();

        $this->_blockGroup = 'oauth';
        $this->_controller = 'adminhtml_oauth_consumer';
        $this->_headerText = Mage::helper('adminhtml')->__('OAuth Consumers');

        //check allow edit
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        if (!$session->isAllowed('system/oauth/consumer/edit')) {
            $this->_removeButton('add');
        }
    }
}
