<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Checkout_Model_Resource_Cart extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote', 'entity_id');
    }

    /**
     * Fetch items summary
     *
     * @param int $quoteId
     * @return array
     */
    public function fetchItemsSummary($quoteId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from(['q' => $this->getTable('sales/quote')], ['items_qty', 'items_count'])
            ->where('q.entity_id = :quote_id');

        $result = $read->fetchRow($select, [':quote_id' => $quoteId]);
        return $result ?: ['items_qty' => 0, 'items_count' => 0];
    }

    /**
     * Fetch items
     *
     * @param int $quoteId
     * @return array
     */
    public function fetchItems($quoteId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from(
                ['qi' => $this->getTable('sales/quote_item')],
                ['id' => 'item_id', 'product_id', 'super_product_id', 'qty', 'created_at'],
            )
            ->where('qi.quote_id = :quote_id');

        return $read->fetchAll($select, [':quote_id' => $quoteId]);
    }

    /**
     * Make collection not to load products that are in specified quote
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $collection
     * @param int $quoteId
     * @return $this
     */
    public function addExcludeProductFilter($collection, $quoteId)
    {
        $adapter = $this->_getReadAdapter();
        $exclusionSelect = $adapter->select()
            ->from($this->getTable('sales/quote_item'), ['product_id'])
            ->where('quote_id = ?', $quoteId);
        $condition = $adapter->prepareSqlCondition('e.entity_id', ['nin' => $exclusionSelect]);
        $collection->getSelect()->where($condition);
        return $this;
    }
}
