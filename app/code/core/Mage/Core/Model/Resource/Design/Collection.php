<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Design_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Core Design resource collection
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/design');
    }

    /**
     * Join store data to collection
     *
     * @return $this
     */
    public function joinStore()
    {
        return $this->join(
            ['cs' => 'core/store'],
            'cs.store_id = main_table.store_id',
            ['cs.name'],
        );
    }

    /**
     * Add date filter to collection
     *
     * @param null|int|string|Zend_Date $date
     * @return $this
     */
    public function addDateFilter($date = null)
    {
        if (is_null($date)) {
            $date = $this->formatDate(true);
        } else {
            $date = $this->formatDate($date);
        }

        $this->addFieldToFilter('date_from', ['lteq' => $date]);
        $this->addFieldToFilter('date_to', ['gteq' => $date]);
        return $this;
    }

    /**
     * Add store filter to collection
     *
     * @param int|array $storeId
     * @return $this
     */
    public function addStoreFilter($storeId)
    {
        return $this->addFieldToFilter('store_id', ['in' => $storeId]);
    }
}
