<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

declare(strict_types=1);

class Mage_Rss_Model_System_Config_Backend_Links extends Mage_Core_Model_Config_Data
{
    /**
     * Invalidate cache type, when value was changed
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->isValueChanged()) {
            Mage::app()->getCache()->invalidateType(Mage_Core_Block_Abstract::CACHE_GROUP);
        }
        return $this;
    }
}
