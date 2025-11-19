<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
     * @return DateTime
     */
    public function getCreatedAtDate()
    {
        return Mage::app()->getLocale()->dateMutable(
            strtotime($this->getCreatedAt()),
            null,
            null,
            true,
        );
    }

    /**
     * Get object created at date affected with object store timezone
     *
     * @return DateTime
     */
    public function getCreatedAtStoreDate()
    {
        return Mage::app()->getLocale()->storeDate(
            $this->getStore(),
            strtotime($this->getCreatedAt()),
            true,
        );
    }
}
