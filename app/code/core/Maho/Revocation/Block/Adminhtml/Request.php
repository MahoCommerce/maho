<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Block_Adminhtml_Request extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'revocation';
        $this->_controller = 'adminhtml_request';
        $this->_headerText = Mage::helper('revocation')->__('Revocation Requests');
        parent::__construct();
        $this->_removeButton('add');
    }
}
