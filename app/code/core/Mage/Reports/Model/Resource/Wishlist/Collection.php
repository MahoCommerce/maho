<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

/**
 * @extends Mage_Core_Model_Resource_Db_Collection_Abstract<Mage_Wishlist_Model_Wishlist>
 */
class Mage_Reports_Model_Resource_Wishlist_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Wishlist table name
     *
     * @var string
     */
    protected $_wishlistTable;

    #[\Override]
    protected function _construct()
    {
        $this->_init('wishlist/wishlist');
        $this->setWishlistTable($this->getTable('wishlist/wishlist'));
    }
    /**
     * Set wishlist table name
     *
     * @param string $value
     * @return $this
     */
    public function setWishlistTable($value)
    {
        $this->_wishlistTable = $value;
        return $this;
    }

    /**
     * retrieve wishlist table name
     *
     * @return string
     */
    public function getWishlistTable()
    {
        return $this->_wishlistTable;
    }

    /**
     * Retrieve wishlist customer count
     *
     * @return array
     */
    public function getWishlistCustomerCount()
    {
        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('customer/customer_collection');

        $customersSelect = $collection->getSelectCountSql();

        $countSelect = clone $customersSelect;
        $countSelect->join(
            ['wt' => $this->getWishlistTable()],
            'wt.customer_id = e.entity_id',
            [],
        );
        $resultSelect = $this->getConnection()->select()
            ->union([$customersSelect, $countSelect], Maho\Db\Select::SQL_UNION_ALL);
        [$customers, $count] = $this->getConnection()->fetchCol($resultSelect);

        return [$customers ? ($count * 100) / $customers : 0, $count];
    }

    /**
     * Get shared items collection count
     *
     * @return false|string|null
     */
    public function getSharedCount()
    {
        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('customer/customer_collection');
        $countSelect = $collection->getSelectCountSql();
        $countSelect->join(
            ['wt' => $this->getWishlistTable()],
            'wt.customer_id=e.entity_id',
            [],
        )
            ->where('wt.shared=1');
        return $countSelect->getAdapter()->fetchOne($countSelect);
    }
}
