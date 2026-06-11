<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Language_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Define resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/language');
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('language_code', 'language_title', ['title' => 'language_title']);
    }

    /**
     * Convert items array to hash for select options
     *
     * @return  array
     */
    #[\Override]
    public function toOptionHash()
    {
        return $this->_toOptionHash('language_code', 'language_title');
    }
}
