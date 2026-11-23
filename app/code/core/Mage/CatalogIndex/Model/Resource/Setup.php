<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

class Mage_CatalogIndex_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{
    /**
     * @var array<int, array<int, int>>|null
     */
    protected $_storeToWebsite   = null;

    public function convertStoreToWebsite(string $table): static
    {
        $assignment = $this->_getStoreToWebsiteAssignments();
        foreach ($assignment as $websiteId => $storeIds) {
            $this->_setWebsiteInfo($table, $websiteId, $storeIds);
        }
        return $this;
    }

    /**
     * @return array<int, array<int, int>> Store IDs by website ID
     */
    protected function _getStoreToWebsiteAssignments(): array
    {
        if (is_null($this->_storeToWebsite)) {
            $this->_storeToWebsite = [];
            $websiteCollection = Mage::getModel('core/website')->getCollection();
            foreach ($websiteCollection as $website) {
                $this->_storeToWebsite[$website->getId()] = $website->getStoreIds();
            }
        }

        return $this->_storeToWebsite;
    }

    /**
     * @param array<int, int> $storeIds
     */
    protected function _setWebsiteInfo(string $table, int $websiteId, array $storeIds): static
    {
        $this->getConnection()->update(
            $table,
            ['website_id' => $websiteId],
            $this->getConnection()->quoteInto('store_id IN (?)', $storeIds),
        );

        return $this;
    }
}
