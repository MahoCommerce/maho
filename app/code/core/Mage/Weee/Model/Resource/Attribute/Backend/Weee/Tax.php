<?php

/**
 * Maho
 *
 * @package    Mage_Weee
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Weee_Model_Resource_Attribute_Backend_Weee_Tax extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Defines main resource table and table identifier field
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('weee/tax', 'value_id');
    }

    /**
     * Load product data
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return array
     */
    public function loadProductData($product, $attribute)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), [
                'website_id',
                'country',
                'state',
                'value',
            ])
            ->where('entity_id = ?', (int) $product->getId())
            ->where('attribute_id = ?', (int) $attribute->getId());
        if ($attribute->isScopeGlobal()) {
            $select->where('website_id = ?', 0);
        } else {
            $storeId = $product->getStoreId();
            if ($storeId) {
                $select->where('website_id IN (?)', [0, Mage::app()->getStore($storeId)->getWebsiteId()]);
            }
        }
        return $this->_getReadAdapter()->fetchAll($select);
    }

    /**
     * Delete product data
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return $this
     */
    public function deleteProductData($product, $attribute)
    {
        $where = [
            'entity_id = ?'    => (int) $product->getId(),
            'attribute_id = ?' => (int) $attribute->getId(),
        ];

        $adapter   = $this->_getWriteAdapter();
        if (!$attribute->isScopeGlobal()) {
            $storeId = $product->getStoreId();
            if ($storeId) {
                $where['website_id IN(?)'] =  [0, Mage::app()->getStore($storeId)->getWebsiteId()];
            }
        }
        $adapter->delete($this->getMainTable(), $where);
        return $this;
    }

    /**
     * Insert product data
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $data
     * @return $this
     */
    public function insertProductData($product, $data)
    {
        $data['entity_id']      = (int) $product->getId();
        $data['entity_type_id'] = (int) $product->getEntityTypeId();

        $this->_getWriteAdapter()->insert($this->getMainTable(), $data);
        return $this;
    }
}
