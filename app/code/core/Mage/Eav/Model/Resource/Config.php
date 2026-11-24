<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @phpstan-import-type DbValue from \Maho\Db\Adapter\AdapterInterface
 */
class Mage_Eav_Model_Resource_Config extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Array of entity types: the rows of the entity type table by ID in by_id, and by code in by_code
     *
     * @var array<string, array<int|string, array<string, DbValue>>>
     */
    protected static $_entityTypes   = [];

    /**
     * Array of attributes: the rows of the attribute table by entity type ID
     *
     * @var array<int|string, list<array<string, DbValue>>>
     */
    protected static $_attributes    = [];

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('eav/entity_type', 'entity_type_id');
    }

    /**
     * Load all entity types
     *
     * @return $this
     */
    protected function _loadTypes()
    {
        $adapter = $this->_getReadAdapter();
        if (!$adapter) {
            return $this;
        }
        if (empty(self::$_entityTypes)) {
            $select = $adapter->select()->from($this->getMainTable());
            $data   = $adapter->fetchAll($select);
            foreach ($data as $row) {
                self::$_entityTypes['by_id'][$row['entity_type_id']] = $row;
                self::$_entityTypes['by_code'][$row['entity_type_code']] = $row;
            }
        }

        return $this;
    }

    /**
     * Load attribute types
     *
     * @param int $typeId
     * @return list<array<string, DbValue>>
     */
    protected function _loadTypeAttributes($typeId)
    {
        if (!isset(self::$_attributes[$typeId])) {
            $adapter = $this->_getReadAdapter();
            $bind    = ['entity_type_id' => $typeId];
            $select  = $adapter->select()
                ->from($this->getTable('eav/attribute'))
                ->where('entity_type_id = :entity_type_id');

            self::$_attributes[$typeId] = $adapter->fetchAll($select, $bind);
        }

        return self::$_attributes[$typeId];
    }

    /**
     * Retrieve entity type data
     *
     * @param string $entityType
     * @return array{entity?: array<string, DbValue>, attributes?: array<int|string, array<string, DbValue>|DbValue>} The attribute rows by attribute ID, and the attribute IDs by attribute code
     */
    public function fetchEntityTypeData($entityType)
    {
        $this->_loadTypes();

        if (is_numeric($entityType)) {
            $info = self::$_entityTypes['by_id'][$entityType] ?? null;
        } else {
            $info = self::$_entityTypes['by_code'][$entityType] ?? null;
        }

        $data = [];
        if ($info) {
            $data['entity']     = $info;
            $attributes         = $this->_loadTypeAttributes($info['entity_type_id']);
            $data['attributes'] = [];
            foreach ($attributes as $attribute) {
                $data['attributes'][$attribute['attribute_id']] = $attribute;
                $data['attributes'][$attribute['attribute_code']] = $attribute['attribute_id'];
            }
        }

        return $data;
    }
}
