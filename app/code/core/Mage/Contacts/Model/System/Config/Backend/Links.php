<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

class Mage_Contacts_Model_System_Config_Backend_Links extends Mage_Adminhtml_Model_System_Config_Backend_Cache
{
    /**
     * Cache tags to clean
     * @var array
     */
    protected $_cacheTags = [Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Block::CACHE_TAG];
}
