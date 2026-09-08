<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_apiplatform_oauth';
        $this->_blockGroup = 'apiplatform';
        $this->_headerText = Mage::helper('apiplatform')->__('Connected Applications');
        parent::__construct();
        $this->_removeButton('add');
    }
}
