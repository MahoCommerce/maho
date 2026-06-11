<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

class Mage_ProductAlert_Model_Resource_Price_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Define price collection
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('productalert/price');
    }

    /**
     * Add customer filter
     *
     * @param mixed $customer
     * @return $this
     */
    public function addCustomerFilter($customer)
    {
        if (is_array($customer)) {
            $condition = $this->getConnection()->quoteInto('customer_id IN(?)', $customer);
        } elseif ($customer instanceof Mage_Customer_Model_Customer) {
            $condition = $this->getConnection()->quoteInto('customer_id=?', $customer->getId());
        } else {
            $condition = $this->getConnection()->quoteInto('customer_id=?', $customer);
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
        if (is_null($website) || $website == 0) {
            return $this;
        }
        if (is_array($website)) {
            $condition = $this->getConnection()->quoteInto('website_id IN(?)', $website);
        } elseif ($website instanceof Mage_Core_Model_Website) {
            $condition = $this->getConnection()->quoteInto('website_id=?', $website->getId());
        } else {
            $condition = $this->getConnection()->quoteInto('website_id=?', $website);
        }
        $this->addFilter('website_id', $condition, 'string');
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
