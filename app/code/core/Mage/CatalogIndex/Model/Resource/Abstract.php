<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

class Mage_CatalogIndex_Model_Resource_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    protected ?int $_storeId = 0;

    protected ?int $_websiteId = null;

    #[\Override]
    protected function _construct(): void {}

    /**
     * storeId setter
     *
     * @param int $storeId
     */
    public function setStoreId($storeId): void
    {
        $this->_storeId = $storeId;
    }

    /**
     * storeId getter
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * websiteId getter
     *
     * @return int
     */
    public function getWebsiteId()
    {
        if (is_null($this->_websiteId)) {
            $result = Mage::app()->getStore($this->getStoreId())->getWebsiteId();
            $this->_websiteId = $result;
        }
        return $this->_websiteId;
    }
}
