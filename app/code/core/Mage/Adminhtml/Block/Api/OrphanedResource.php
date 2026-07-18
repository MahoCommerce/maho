<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Api_OrphanedResource extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'api_orphanedResource';
        $this->_headerText = Mage::helper('adminhtml')->__('Orphaned API Role Resources');
        parent::__construct();
        $this->_removeButton('add');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        Mage::dispatchEvent('api_orphanedresource_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}
