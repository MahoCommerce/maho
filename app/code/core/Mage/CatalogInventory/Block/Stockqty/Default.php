<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

class Mage_CatalogInventory_Block_Stockqty_Default extends Mage_CatalogInventory_Block_Stockqty_Abstract
{
    /**
     * Render block HTML
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->isMsgVisible()) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Retrieve block cache tags
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        return array_merge(parent::getCacheTags(), $this->_getProduct()->getCacheIdTags());
    }
}
