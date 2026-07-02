<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Template extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    #[\Override]
    protected function _getUrlModelClass()
    {
        return 'adminhtml/url';
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string
     */
    #[\Override]
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    /**
     * Prepare html output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_block_html_before', ['block' => $this]);
        return parent::_toHtml();
    }

    /**
     * Deleting script tags from string
     *
     * @param string $html
     * @return string
     */
    public function maliciousCodeFilter($html)
    {
        return Mage::getSingleton('core/input_filter_maliciousCode')->filter($html);
    }
}
