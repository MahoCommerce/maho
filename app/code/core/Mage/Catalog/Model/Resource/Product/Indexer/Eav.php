<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Product_Indexer_Eav extends Mage_Catalog_Model_Resource_Product_Indexer_Abstract
{
    /**
     * EAV Indexers by type
     *
     * @var array|null
     */
    protected $_types;

    /**
     * Define main index table
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_index_eav', 'entity_id');
    }

    /**
     * Retrieve array of EAV type indexers
     *
     * @return array
     */
    public function getIndexers()
    {
        if (is_null($this->_types)) {
            $this->_types   = [
                'source'    => Mage::getResourceModel('catalog/product_indexer_eav_source'),
                'decimal'   => Mage::getResourceModel('catalog/product_indexer_eav_decimal'),
            ];
        }

        return $this->_types;
    }

    /**
     * Retrieve indexer instance by type
     *
     * @param string $type
     * @return Mage_Catalog_Model_Resource_Product_Indexer_Eav_Abstract
     */
    public function getIndexer($type)
    {
        $indexers = $this->getIndexers();
        if (!isset($indexers[$type])) {
            Mage::throwException(Mage::helper('catalog')->__('Unknown EAV indexer type "%s".', $type));
        }
        return $indexers[$type];
    }

    /**
     * Process product save.
     * Method is responsible for index support
     * when product was saved and assigned categories was changed.
     *
     * @return $this
     */
    public function catalogProductSave(Mage_Index_Model_Event $event)
    {
        $productId = $event->getEntityPk();
        $data = $event->getNewData();

        /**
         * Check if filterable attribute values were updated
         */
        if (!isset($data['reindex_eav'])) {
            return $this;
        }

        foreach ($this->getIndexers() as $indexer) {
            /** @var Mage_Catalog_Model_Resource_Product_Indexer_Eav_Abstract $indexer */
            $indexer->reindexEntities($productId);
        }

        return $this;
    }

    /**
     * Process Product Delete
     *
     * @return $this
     */
    public function catalogProductDelete(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (empty($data['reindex_eav_parent_ids'])) {
            return $this;
        }

        foreach ($this->getIndexers() as $indexer) {
            /** @var Mage_Catalog_Model_Resource_Product_Indexer_Eav_Abstract $indexer */
            $indexer->reindexEntities($data['reindex_eav_parent_ids']);
        }

        return $this;
    }

    /**
     * Process Product Mass Update
     *
     * @return $this
     */
    public function catalogProductMassAction(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (empty($data['reindex_eav_product_ids'])) {
            return $this;
        }

        foreach ($this->getIndexers() as $indexer) {
            /** @var Mage_Catalog_Model_Resource_Product_Indexer_Eav_Abstract $indexer */
            $indexer->reindexEntities($data['reindex_eav_product_ids']);
        }

        return $this;
    }

    /**
     * Process Catalog Eav Attribute Save
     *
     * @return $this
     */
    public function catalogEavAttributeSave(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (empty($data['reindex_attribute'])) {
            return $this;
        }

        $indexer = $this->getIndexer($data['attribute_index_type']);

        $indexer->reindexAttribute($event->getEntityPk(), !empty($data['is_indexable']));

        return $this;
    }

    /**
     * Rebuild all index data
     *
     * @return $this
     */
    #[\Override]
    public function reindexAll()
    {
        $this->useIdxTable(true);
        foreach ($this->getIndexers() as $indexer) {
            /** @var Mage_Catalog_Model_Resource_Product_Indexer_Eav_Abstract $indexer */
            $indexer->reindexAll();
        }

        return $this;
    }

    /**
     * Retrieve temporary source index table name
     *
     * @param string $table
     * @return string
     */
    #[\Override]
    public function getIdxTable($table = null)
    {
        if ($this->useIdxTable()) {
            return $this->getTable('catalog/product_eav_indexer_idx');
        }
        return $this->getTable('catalog/product_eav_indexer_tmp');
    }
}
