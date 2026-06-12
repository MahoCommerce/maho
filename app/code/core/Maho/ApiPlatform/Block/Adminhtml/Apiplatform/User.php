<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_apiplatform_user';
        $this->_blockGroup = 'apiplatform';
        $this->_headerText = Mage::helper('apiplatform')->__('REST/GraphQL - Users');
        $this->_addButtonLabel = Mage::helper('apiplatform')->__('Add New User');
        parent::__construct();
    }
}
