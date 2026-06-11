<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Catalog_Search_Separator extends Mage_Core_Model_Config_Data
{
    /**
     * After change Catalog Search Type process
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        $newValue = $this->getValue();
        $oldValue = Mage::getConfig()->getNode(
            Mage_CatalogSearch_Model_Fulltext::XML_PATH_CATALOG_SEARCH_SEPARATOR,
            $this->getScope(),
            $this->getScopeId(),
        );
        if ($newValue != $oldValue) {
            Mage::getSingleton('catalogsearch/fulltext')->resetSearchResults();
        }

        return $this;
    }
}
