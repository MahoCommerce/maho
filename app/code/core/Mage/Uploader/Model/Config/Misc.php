<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Uploader
 */

/**
 * Misc Config Parameters
 *
 * @package    Mage_Uploader
 *
 * @method $this setMaxSizePlural (string $sizePlural) Set plural info about max upload size
 * @method $this setMaxSizeInBytes (int $sizeInBytes) Set max upload size in bytes
 * @method $this setReplaceBrowseWithRemove (bool $replaceBrowseWithRemove)
 *      Replace browse button with remove after selecting file
 */
class Mage_Uploader_Model_Config_Misc extends Mage_Uploader_Model_Config_Abstract
{
    /**
     * Prepare misc params
     */
    #[\Override]
    protected function _construct()
    {
        $this
            ->setMaxSizeInBytes($this->_getHelper()->getDataMaxSizeInBytes())
            ->setMaxSizePlural($this->_getHelper()->getDataMaxSize());
    }
}
