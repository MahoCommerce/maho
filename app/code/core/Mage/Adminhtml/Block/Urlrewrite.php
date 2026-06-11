<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Urlrewrite extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Part for generating appropriate grid block name
     *
     * @var string
     */
    protected $_controller = 'urlrewrite';

    /**
     * Set custom labels and headers
     */
    public function __construct()
    {
        $this->_headerText = Mage::helper('adminhtml')->__('URL Rewrite Management');
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add URL Rewrite');
        parent::__construct();
    }

    /**
     * Customize grid row URLs
     *
     * @see Mage_Adminhtml_Block_Urlrewrite_Selector
     * @return string
     */
    #[\Override]
    public function getCreateUrl()
    {
        $modes = array_keys(Mage::getBlockSingleton('adminhtml/urlrewrite_selector')->getModes());
        return $this->getUrl('*/*/edit') . array_shift($modes);
    }
}
