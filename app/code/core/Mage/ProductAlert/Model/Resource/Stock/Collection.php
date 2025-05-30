<?php

/**
 * Maho
 *
 * @package    Mage_ProductAlert
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ProductAlert_Model_Resource_Stock_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Define stock collection
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('productalert/stock');
    }

    /**
     * Add customer filter
     *
     * @param mixed $customer
     * @return $this
     */
    public function addCustomerFilter($customer)
    {
        $adapter = $this->getConnection();
        if (is_array($customer)) {
            $condition = $adapter->quoteInto('customer_id IN(?)', $customer);
        } elseif ($customer instanceof Mage_Customer_Model_Customer) {
            $condition = $adapter->quoteInto('customer_id=?', $customer->getId());
        } else {
            $condition = $adapter->quoteInto('customer_id=?', $customer);
        }
        $this->addFilter('customer_id', $condition, 'string');
        return $this;
    }

    /**
     * Add website filter
     *
     * @param mixed $website
     * @return $this
     */
    public function addWebsiteFilter($website)
    {
        $adapter = $this->getConnection();
        if (is_null($website) || $website == 0) {
            return $this;
        }
        if (is_array($website)) {
            $condition = $adapter->quoteInto('website_id IN(?)', $website);
        } elseif ($website instanceof Mage_Core_Model_Website) {
            $condition = $adapter->quoteInto('website_id=?', $website->getId());
        } else {
            $condition = $adapter->quoteInto('website_id=?', $website);
        }
        $this->addFilter('website_id', $condition, 'string');
        return $this;
    }

    /**
     * Add status filter
     *
     * @param int $status
     * @return $this
     */
    public function addStatusFilter($status)
    {
        $condition = $this->getConnection()->quoteInto('status=?', $status);
        $this->addFilter('status', $condition, 'string');
        return $this;
    }

    /**
     * Set order by customer
     *
     * @param string $sort
     * @return $this
     */
    public function setCustomerOrder($sort = 'ASC')
    {
        $this->getSelect()->order('customer_id ' . $sort);
        return $this;
    }
}
