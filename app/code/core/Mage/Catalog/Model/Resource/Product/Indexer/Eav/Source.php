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

class Mage_Catalog_Model_Resource_Product_Indexer_Eav_Source extends Mage_Catalog_Model_Resource_Product_Indexer_Eav_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_index_eav', 'entity_id');
    }

    /**
     * Retrieve indexable eav attribute ids
     *
     * @param bool $multiSelect
     * @return array
     */
    protected function _getIndexableAttributes($multiSelect)
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['ca' => $this->getTable('catalog/eav_attribute')], 'attribute_id')
            ->join(
                ['ea' => $this->getTable('eav/attribute')],
                'ca.attribute_id = ea.attribute_id',
                [],
            )
            ->where($this->_getIndexableAttributesCondition());

        if ($multiSelect == true) {
            $select->where('ea.backend_type = ?', 'text')
                ->where('ea.frontend_input = ?', 'multiselect');
        } else {
            $select->where('ea.backend_type = ?', 'int')
                ->where('ea.frontend_input = ?', 'select');
        }

        return $this->_getReadAdapter()->fetchCol($select);
    }

    /**
     * Prepare data index for indexable attributes
     *
     * @param array $entityIds      the entity ids limitation
     * @param int $attributeId      the attribute id limitation
     * @return $this
     */
    #[\Override]
    protected function _prepareIndex($entityIds = null, $attributeId = null)
    {
        $this->_prepareSelectIndex($entityIds, $attributeId);
        $this->_prepareMultiselectIndex($entityIds, $attributeId);

        return $this;
    }

    /**
     * Prepare data index for indexable select attributes
     *
     * @param array $entityIds      the entity ids limitation
     * @param int $attributeId      the attribute id limitation
     * @return $this
     */
    protected function _prepareSelectIndex($entityIds = null, $attributeId = null)
    {
        $adapter    = $this->_getWriteAdapter();
        $idxTable   = $this->getIdxTable();
        // prepare select attributes
        if (is_null($attributeId)) {
            $attrIds    = $this->_getIndexableAttributes(false);
        } else {
            $attrIds    = [$attributeId];
        }

        if (!$attrIds) {
            return $this;
        }

        $subSelect = $adapter->select()
            ->from(
                ['s' => $this->getTable('core/store')],
                ['store_id', 'website_id'],
            )
            ->joinLeft(
                ['d' => $this->getValueTable('catalog/product', 'int')],
                '1 = 1 AND d.store_id = 0',
                ['entity_id', 'attribute_id', 'value'],
            )
            ->where('s.store_id != ? AND s.is_active=1', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);

        $statusCond = $adapter->quoteInto(' = ?', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $this->_addAttributeToSelect($subSelect, 'status', 'd.entity_id', 's.store_id', $statusCond);

        if (!is_null($entityIds)) {
            $subSelect->where('d.entity_id IN(?)', $entityIds);
        }

        $select = $adapter->select()
            ->from(
                ['pid' => new Zend_Db_Expr(sprintf('(%s)', $subSelect->assemble()))],
                [],
            )
            ->joinLeft(
                ['pis' => $this->getValueTable('catalog/product', 'int')],
                'pis.entity_id = pid.entity_id AND pis.attribute_id = pid.attribute_id AND pis.store_id = pid.store_id',
                [],
            )
            ->columns(
                [
                    'pid.entity_id',
                    'pid.attribute_id',
                    'pid.store_id',
                    'value' => $adapter->getIfNullSql('pis.value', 'pid.value'),
                ],
            )
            ->where('pid.attribute_id IN(?)', $attrIds);

        /** @var Mage_Catalog_Model_Resource_Helper_Mysql4 $helper */
        $helper = Mage::getResourceHelper('catalog');
        $select->where($helper->getIsNullNotNullCondition('pis.value', 'pid.value'));

        /**
         * Add additional external limitation
         */
        Mage::dispatchEvent('prepare_catalog_product_index_select', [
            'select'        => $select,
            'entity_field'  => new Zend_Db_Expr('pid.entity_id'),
            'website_field' => new Zend_Db_Expr('pid.website_id'),
            'store_field'   => new Zend_Db_Expr('pid.store_id'),
        ]);

        $query = $select->insertFromSelect($idxTable);
        $adapter->query($query);

        return $this;
    }

    /**
     * Prepare data index for indexable multiply select attributes
     *
     * @param array $entityIds      the entity ids limitation
     * @param int $attributeId      the attribute id limitation
     * @return $this
     */
    protected function _prepareMultiselectIndex($entityIds = null, $attributeId = null)
    {
        $adapter    = $this->_getWriteAdapter();

        // prepare multiselect attributes
        if (is_null($attributeId)) {
            $attrIds    = $this->_getIndexableAttributes(true);
        } else {
            $attrIds    = [$attributeId];
        }

        if (!$attrIds) {
            return $this;
        }

        // load attribute options
        $options = [];
        $select  = $adapter->select()
            ->from($this->getTable('eav/attribute_option'), ['attribute_id', 'option_id'])
            ->where('attribute_id IN(?)', $attrIds);
        $query = $select->query();
        while ($row = $query->fetch()) {
            $options[$row['attribute_id']][$row['option_id']] = true;
        }

        // prepare get multiselect values query
        $productValueExpression = $adapter->getCheckSql('pvs.value_id > 0', 'pvs.value', 'pvd.value');
        $select = $adapter->select()
            ->from(
                ['pvd' => $this->getValueTable('catalog/product', 'text')],
                ['entity_id', 'attribute_id'],
            )
            ->join(
                ['cs' => $this->getTable('core/store')],
                '',
                ['store_id'],
            )
            ->joinLeft(
                ['pvs' => $this->getValueTable('catalog/product', 'text')],
                'pvs.entity_id = pvd.entity_id AND pvs.attribute_id = pvd.attribute_id'
                    . ' AND pvs.store_id=cs.store_id',
                ['value' => $productValueExpression],
            )
            ->where(
                'pvd.store_id=?',
                $adapter->getIfNullSql('pvs.store_id', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID),
            )
            ->where('cs.store_id != ? AND cs.is_active=1', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->where('pvd.attribute_id IN(?)', $attrIds);

        $statusCond = $adapter->quoteInto('=?', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $this->_addAttributeToSelect($select, 'status', 'pvd.entity_id', 'cs.store_id', $statusCond);

        if (!is_null($entityIds)) {
            $select->where('pvd.entity_id IN(?)', $entityIds);
        }

        /**
         * Add additional external limitation
         */
        Mage::dispatchEvent('prepare_catalog_product_index_select', [
            'select'        => $select,
            'entity_field'  => new Zend_Db_Expr('pvd.entity_id'),
            'website_field' => new Zend_Db_Expr('cs.website_id'),
            'store_field'   => new Zend_Db_Expr('cs.store_id'),
        ]);

        $i     = 0;
        $data  = [];
        $query = $select->query();
        while ($row = $query->fetch()) {
            $values = empty($row['value']) ? [] : array_unique(explode(',', $row['value']));
            foreach ($values as $valueId) {
                if (isset($options[$row['attribute_id']][$valueId])) {
                    $data[] = [
                        $row['entity_id'],
                        $row['attribute_id'],
                        $row['store_id'],
                        $valueId,
                    ];
                    $i++;
                    if ($i % 10000 == 0) {
                        $this->_saveIndexData($data);
                        $data = [];
                    }
                }
            }
        }

        $this->_saveIndexData($data);
        unset($options);
        unset($data);

        return $this;
    }

    /**
     * Save a data to temporary source index table
     *
     * @return $this
     */
    protected function _saveIndexData(array $data)
    {
        if (!$data) {
            return $this;
        }
        $adapter = $this->_getWriteAdapter();
        $adapter->insertArray($this->getIdxTable(), ['entity_id', 'attribute_id', 'store_id', 'value'], $data);
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
