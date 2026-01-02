<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Model_Resource_Fulltext_Engine extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Init resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogsearch/fulltext', 'product_id');
    }

    /**
     * Add entity data to fulltext search table
     *
     * @param int $entityId
     * @param int $storeId
     * @param array $index
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function saveEntityIndex($entityId, $storeId, $index, $entity = 'product')
    {
        $this->_getWriteAdapter()->insert($this->getMainTable(), [
            'product_id'    => $entityId,
            'store_id'      => $storeId,
            'data_index'    => $index,
        ]);
        return $this;
    }

    /**
     * Multi add entities data to fulltext search table
     *
     * @param int $storeId
     * @param array $entityIndexes
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function saveEntityIndexes($storeId, $entityIndexes, $entity = 'product')
    {
        $data    = [];
        $storeId = (int) $storeId;
        foreach ($entityIndexes as $entityId => $index) {
            $data[] = [
                'product_id'    => (int) $entityId,
                'store_id'      => $storeId,
                'data_index'    => $index,
            ];
        }

        if ($data) {
            $helper = Mage::getResourceHelper('catalogsearch');
            $helper->insertOnDuplicate($this->getMainTable(), $data, ['data_index']);
        }

        return $this;
    }

    /**
     * Retrieve allowed visibility values for current engine
     *
     * @return array
     */
    public function getAllowedVisibility()
    {
        return Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
    }

    /**
     * Define if current search engine supports advanced index
     *
     * @return bool
     */
    public function allowAdvancedIndex()
    {
        return false;
    }

    /**
     * Remove entity data from fulltext search table
     *
     * @param int $storeId
     * @param int $entityId
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function cleanIndex($storeId = null, $entityId = null, $entity = 'product')
    {
        $where = [];

        if (!is_null($storeId)) {
            $where[] = $this->_getWriteAdapter()->quoteInto('store_id=?', $storeId);
        }
        if (!is_null($entityId)) {
            $where[] = $this->_getWriteAdapter()->quoteInto('product_id IN (?)', $entityId);
        }

        $this->_getWriteAdapter()->delete($this->getMainTable(), $where);

        return $this;
    }

    /**
     * Prepare index array as a string glued by separator
     *
     * @param array $index
     * @param string $separator
     * @return string
     */
    public function prepareEntityIndex($index, $separator = ' ')
    {
        return Mage::helper('catalogsearch')->prepareIndexdata($index, $separator);
    }

    /**
     * Stub method for compatibility with other search engines
     */
    public function getResourceName()
    {
        return null;
    }

    /**
     * Retrieve fulltext search result data collection
     *
     * @return Mage_CatalogSearch_Model_Resource_Fulltext_Collection
     */
    public function getResultCollection()
    {
        return Mage::getResourceModel('catalogsearch/fulltext_collection');
    }

    /**
     * Retrieve advanced search result data collection
     *
     * @return Mage_CatalogSearch_Model_Resource_Advanced_Collection
     */
    public function getAdvancedResultCollection()
    {
        return Mage::getResourceModel('catalogsearch/advanced_collection');
    }

    /**
     * Define if Layered Navigation is allowed
     *
     * @return bool
     */
    public function isLeyeredNavigationAllowed()
    {
        return true;
    }

    /**
     * Define if engine is available
     *
     * @return bool
     */
    public function test()
    {
        return true;
    }
}
