<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

abstract class Mage_Sitemap_Model_Resource_Catalog_Abstract extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Collection Zend Db select
     *
     * @var Maho\Db\Select
     */
    protected $_select;

    /**
     * Attribute cache
     *
     * @var array
     */
    protected $_attributesCache = [];

    /**
     * Entity type code of the entities whose attributes loadAttributesForIds() reads
     */
    protected string $_attributeEntityType;

    /**
     * Catalog factory instance
     *
     * @var Mage_Catalog_Model_Factory
     */
    protected $_factory;

    /**
     * Initialize factory instance
     */
    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('catalog/factory') : $args['factory'];
        parent::__construct();
    }

    /**
     * Retrieve catalog collection
     *
     * @param int $storeId
     * @return array
     */
    abstract public function getCollection($storeId);

    /**
     * Load attributes for specific entity IDs
     */
    public function loadAttributesForIds(array $entityIds, int $storeId): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $nameAttr = Mage::getSingleton('eav/config')->getAttribute($this->_attributeEntityType, 'name');
        $imageAttr = Mage::getSingleton('eav/config')->getAttribute($this->_attributeEntityType, 'image');
        $attributeMap = [
            $nameAttr->getId() => 'name',
            $imageAttr->getId() => 'image',
        ];

        $read = $this->_getReadAdapter();
        // Use CASE instead of MySQL's FIELD() for cross-database compatibility (SQLite)
        // Store-specific values ($storeId) should come first, then default (0)
        $storeOrder = new \Maho\Db\Expr(
            'CASE WHEN store_id = ' . (int) $storeId . ' THEN 0 WHEN store_id = 0 THEN 1 ELSE 2 END',
        );
        $select = $read->select()
            ->from(['attr' => $this->_attributeEntityType . '_entity_varchar'], ['entity_id', 'attribute_id', 'value', 'store_id'])
            ->where('attr.entity_id IN (?)', $entityIds)
            ->where('attr.attribute_id IN (?)', array_keys($attributeMap))
            ->where('attr.store_id IN (?)', [0, $storeId])
            ->order(['entity_id ASC', 'attribute_id ASC', $storeOrder]);

        $query = $read->query($select);
        $attributes = [];

        // Process results with store fallback logic
        while ($row = $query->fetch()) {
            $entityId = $row['entity_id'];
            $attributeCode = $attributeMap[$row['attribute_id']];

            // Only set if not already set (store-specific values come first due to ORDER BY)
            if (!isset($attributes[$entityId][$attributeCode])) {
                $attributes[$entityId] ??= [];
                $attributes[$entityId][$attributeCode] = $row['value'];
            }
        }

        return $attributes;
    }

    /**
     * Add attribute to filter
     *
     * @param int $storeId
     * @param string $attributeCode
     * @param mixed $value
     * @param string $type
     * @return Maho\Db\Select|false
     */
    protected function _addFilter($storeId, $attributeCode, $value, $type = '=')
    {
        if (!isset($this->_attributesCache[$attributeCode])) {
            $this->_loadAttribute($attributeCode);
        }

        $attribute = $this->_attributesCache[$attributeCode];

        if (!$this->_select instanceof Maho\Db\Select) {
            return false;
        }

        switch ($type) {
            case '=':
                $conditionRule = '=?';
                break;
            case 'in':
                $conditionRule = ' IN(?)';
                break;
            default:
                return false;
        }

        if ($attribute['backend_type'] == 'static') {
            $this->_select->where('main_table.' . $attributeCode . $conditionRule, $value);
        } else {
            $this->_select->join(
                ['t1_' . $attributeCode => $attribute['table']],
                'main_table.entity_id=t1_' . $attributeCode . '.entity_id AND t1_' . $attributeCode . '.store_id=0',
                [],
            )
                ->where('t1_' . $attributeCode . '.attribute_id=?', $attribute['attribute_id']);

            if ($attribute['is_global']) {
                $this->_select->where('t1_' . $attributeCode . '.value' . $conditionRule, $value);
            } else {
                $ifCase = $this->_select->getAdapter()->getCheckSql(
                    't2_' . $attributeCode . '.value_id > 0',
                    't2_' . $attributeCode . '.value',
                    't1_' . $attributeCode . '.value',
                );
                $this->_select->joinLeft(
                    ['t2_' . $attributeCode => $attribute['table']],
                    $this->_getWriteAdapter()->quoteInto(
                        't1_' . $attributeCode . '.entity_id = t2_' . $attributeCode . '.entity_id AND t1_'
                            . $attributeCode . '.attribute_id = t2_' . $attributeCode . '.attribute_id AND t2_'
                            . $attributeCode . '.store_id = ?',
                        $storeId,
                    ),
                    [],
                )
                ->where('(' . $ifCase . ')' . $conditionRule, $value);
            }
        }

        return $this->_select;
    }

    /**
     * Prepare catalog object
     *
     * @return \Maho\DataObject
     */
    protected function _prepareObject(array $row)
    {
        $entity = new \Maho\DataObject();
        $entity->setId($row[$this->getIdFieldName()]);
        $entity->setUrl($this->_getEntityUrl($row, $entity));

        // Pass through all additional data from the row (like name, image, etc.)
        foreach ($row as $key => $value) {
            if ($key !== $this->getIdFieldName() && !$entity->hasData($key)) {
                $entity->setData($key, $value);
            }
        }

        return $entity;
    }

    /**
     * Load and prepare entities
     *
     * @return array
     */
    protected function _loadEntities()
    {
        $entities = [];
        $query = $this->_getWriteAdapter()->query($this->_select);
        while ($row = $query->fetch()) {
            $entity = $this->_prepareObject($row);
            $entities[$entity->getId()] = $entity;
        }
        return $entities;
    }

    /**
     * Retrieve entity url
     *
     * @param array $row
     * @param \Maho\DataObject $entity
     * @return string
     */
    abstract protected function _getEntityUrl($row, $entity);

    /**
     * Loads attribute by given attribute_code
     *
     * @param string $attributeCode
     * @return Mage_Sitemap_Model_Resource_Catalog_Abstract
     */
    abstract protected function _loadAttribute($attributeCode);
}
