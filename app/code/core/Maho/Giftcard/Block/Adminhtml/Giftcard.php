<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Giftcard extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_giftcard';
        $this->_blockGroup = 'giftcard';
        $this->_headerText = Mage::helper('giftcard')->__('Gift Cards');
        $this->_addButtonLabel = Mage::helper('giftcard')->__('Add New Gift Card');

        parent::__construct();
    }
}
