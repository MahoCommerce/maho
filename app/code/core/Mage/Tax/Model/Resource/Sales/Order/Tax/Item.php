<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Tax_Model_Resource_Sales_Order_Tax_Item extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/sales_order_tax_item', 'tax_item_id');
    }

    /**
     * Get Tax Items with order tax information
     *
     * @param int $itemId
     * @return array
     */
    public function getTaxItemsByItemId($itemId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from(['item' => $this->getTable('tax/sales_order_tax_item')], ['tax_id', 'tax_percent'])
            ->join(
                ['tax' => $this->getTable('tax/sales_order_tax')],
                'item.tax_id = tax.tax_id',
                ['title', 'percent', 'base_amount'],
            )
            ->where('item_id = ?', $itemId);

        return $adapter->fetchAll($select);
    }
}
