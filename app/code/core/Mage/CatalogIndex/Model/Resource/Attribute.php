<?php

/**
 * Maho
 *
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogIndex_Model_Resource_Attribute extends Mage_CatalogIndex_Model_Resource_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/eav', 'index_id');
    }

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param string $filter
     * @param int|array $entityFilter
     * @return array
     */
    public function getFilteredEntities($attribute, $filter, $entityFilter)
    {
        $select = $this->_getReadAdapter()->select();

        $select
            ->from($this->getMainTable(), 'entity_id')
            ->distinct(true)
            ->where('entity_id in (?)', $entityFilter)
            ->where('store_id = ?', $this->getStoreId())
            ->where('attribute_id = ?', $attribute->getId())
            ->where('value = ?', $filter);

        return $this->_getReadAdapter()->fetchCol($select);
    }

    /**
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param Maho\Db\Select $entitySelect
     * @return array
     */
    public function getCount($attribute, $entitySelect)
    {
        $select = clone $entitySelect;
        $select->reset(Maho\Db\Select::COLUMNS);
        $select->reset(Maho\Db\Select::ORDER);
        $select->reset(Maho\Db\Select::LIMIT_COUNT);
        $select->reset(Maho\Db\Select::LIMIT_OFFSET);

        $fields = ['count' => 'COUNT(index.entity_id)', 'index.value'];

        $select->columns($fields)
            ->join(['index' => $this->getMainTable()], 'index.entity_id=e.entity_id', [])
            ->where('index.store_id = ?', $this->getStoreId())
            ->where('index.attribute_id = ?', $attribute->getId())
            ->group('index.value');

        $select = $select->__toString();
        $result = $this->_getReadAdapter()->fetchAll($select);

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['value']] = $row['count'];
        }
        return $counts;
    }

    /**
     * @param Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param string $value
     * @return $this
     */
    public function applyFilterToCollection($collection, $attribute, $value)
    {
        /**
         * Will be used after SQL review
         */
        $alias = 'attr_index_' . $attribute->getId();
        $collection->getSelect()->join(
            [$alias => $this->getMainTable()],
            $alias . '.entity_id=e.entity_id',
            [],
        )
        ->where($alias . '.store_id = ?', $this->getStoreId())
        ->where($alias . '.attribute_id = ?', $attribute->getId())
        ->where($alias . '.value = ?', $value);
        return $this;
    }
}
