<?php

/**
 * Maho
 *
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogIndex_Model_Resource_Retreiver extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product', 'entity_id');
    }

    /**
     * Return id-type pairs
     *
     * @param array $ids
     * @return array
     */
    public function getProductTypes($ids)
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['main_table' => $this->getTable('catalog/product')], ['id' => 'main_table.entity_id', 'type' => 'main_table.type_id'])
            ->where('main_table.entity_id in (?)', $ids);
        return $this->_getReadAdapter()->fetchAll($select);
    }
}
