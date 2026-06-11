<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Sales abstract model
 * Provide date processing functionality
 *
 * @method Mage_Sales_Model_Resource_Order_Abstract _getResource()
 * @method $this setTransactionId(int $value)
 * @method bool getForceUpdateGridRecords()
 */
abstract class Mage_Sales_Model_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * Get object store identifier
     *
     * @return int|string|Mage_Core_Model_Store
     */
    abstract public function getStore();

    /**
     * Processing object after save data
     * Updates relevant grid table records.
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    public function afterCommitCallback()
    {
        if (!$this->getForceUpdateGridRecords()) {
            $this->_getResource()->updateGridRecords($this->getId());
        }
        return parent::afterCommitCallback();
    }

    /**
     * Get object created at date affected current active store timezone
     *
     * @return DateTimeImmutable
     */
    public function getCreatedAtDate()
    {
        return Mage::app()->getLocale()->utcToStore(null, strtotime($this->getCreatedAt()));
    }

    /**
     * Get object created at date affected with object store timezone
     *
     * @return DateTimeImmutable
     */
    public function getCreatedAtStoreDate()
    {
        return Mage::app()->getLocale()->utcToStore(
            $this->getStore(),
            $this->getCreatedAt(),
        );
    }
}
