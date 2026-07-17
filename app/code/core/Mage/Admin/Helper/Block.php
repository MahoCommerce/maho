<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Helper_Block extends Mage_Core_Helper_Abstract
{
    /**
     * Types cache
     *
     * @var array
     */
    protected $_allowedTypes;

    public function __construct()
    {
        $this->_allowedTypes = Mage::getResourceModel('admin/block')->getAllowedTypes();
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isTypeAllowed($type)
    {
        return isset($this->_allowedTypes[$type]);
    }

    /**
     *  Get disallowed names for block
     *
     * @return array
     */
    public function getDisallowedBlockNames()
    {
        return Mage::getResourceModel('admin/block')->getDisallowedBlockNames();
    }
}
