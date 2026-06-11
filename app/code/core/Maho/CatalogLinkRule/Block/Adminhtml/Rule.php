<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Block_Adminhtml_Rule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_rule';
        $this->_blockGroup = 'cataloglinkrule';
        $this->_headerText = Mage::helper('cataloglinkrule')->__('Product Relationship Rules');
        $this->_addButtonLabel = Mage::helper('cataloglinkrule')->__('Add New Rule');
        parent::__construct();
    }
}
