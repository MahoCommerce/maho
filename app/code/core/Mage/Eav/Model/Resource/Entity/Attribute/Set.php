<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Entity_Attribute_Set extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/attribute_set', 'attribute_set_id');
    }

    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        if ($object->getGroups()) {
            foreach ($object->getGroups() as $group) {
                $group->setAttributeSetId($object->getId());
                if ($group->itemExists() && !$group->getId()) {
                    continue;
                }
                $group->save();
            }
        }
        if ($object->getRemoveGroups()) {
            foreach ($object->getRemoveGroups() as $group) {
                $group->delete();
            }
            Mage::getResourceModel('eav/entity_attribute_group')->updateDefaultGroup($object->getId());
        }
        if ($object->getRemoveAttributes()) {
            foreach ($object->getRemoveAttributes() as $attribute) {
                $attribute->deleteEntity();
            }
        }

        return parent::_afterSave($object);
    }

    /**
     * Validate attribute set name
     *
     * @param Mage_Eav_Model_Entity_Attribute_Set $object
     * @param string $attributeSetName
     * @return bool
     */
    public function validate($object, $attributeSetName)
    {
        $adapter = $this->_getReadAdapter();
        $bind = [
            'attribute_set_name' => trim($attributeSetName),
            'entity_type_id'     => $object->getEntityTypeId(),
        ];
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('attribute_set_name = :attribute_set_name')
            ->where('entity_type_id = :entity_type_id');

        if ($object->getId()) {
            $bind['attribute_set_id'] = $object->getId();
            $select->where('attribute_set_id != :attribute_set_id');
        }

        return $adapter->fetchOne($select, $bind) ? false : true;
    }

    /**
     * Retrieve Set info by attributes
     *
     * @param int|null $setId
     * @return array
     */
    public function getSetInfo(array $attributeIds = [], $setId = null)
    {
        $adapter = $this->_getReadAdapter();
        $setInfo = [];
        $attributeToSetInfo = [];

        $select = $adapter->select()
            ->from(
                ['entity' => $this->getTable('eav/entity_attribute')],
                ['attribute_id', 'attribute_set_id', 'attribute_group_id', 'sort_order'],
            )
            ->joinLeft(
                ['attribute_group' => $this->getTable('eav/attribute_group')],
                'entity.attribute_group_id = attribute_group.attribute_group_id',
                ['group_sort_order' => 'sort_order'],
            );
        if (count($attributeIds) > 0) {
            $select->where('entity.attribute_id IN (?)', $attributeIds);
        }
        $bind = [];
        if (is_numeric($setId)) {
            $bind[':attribute_set_id'] = $setId;
            $select->where('entity.attribute_set_id = :attribute_set_id');
        }
        $result = $adapter->fetchAll($select, $bind);

        foreach ($result as $row) {
            $data = [
                'group_id' => $row['attribute_group_id'],
                'group_sort' => $row['group_sort_order'],
                'sort' => $row['sort_order'],
            ];
            $attributeToSetInfo[$row['attribute_id']][$row['attribute_set_id']] = $data;
        }

        if (count($attributeIds)) {
            foreach ($attributeIds as $atttibuteId) {
                $setInfo[$atttibuteId] = $attributeToSetInfo[$atttibuteId] ?? [];
            }
        } else {
            return $attributeToSetInfo;
        }

        return $setInfo;
    }

    /**
     * Retrurn default attribute group id for attribute set id
     *
     * @param int $setId
     * @return string
     */
    public function getDefaultGroupId($setId)
    {
        $adapter = $this->_getReadAdapter();
        $bind    = [
            'attribute_set_id' => (int) $setId,
        ];
        $select = $adapter->select()
            ->from($this->getTable('eav/attribute_group'), 'attribute_group_id')
            ->where('attribute_set_id = :attribute_set_id')
            ->where('default_id = 1')
            ->limit(1);
        return $adapter->fetchOne($select, $bind);
    }

    /**
     * Return the labels of the attributes that set $fromSetId holds and set $toSetId does not.
     * A record that moves from the first set to the second loses its values of these attributes.
     *
     * @return list<string>
     */
    public function getMissingAttributeLabels(int $fromSetId, int $toSetId): array
    {
        $adapter = $this->_getReadAdapter();
        $entityAttributeTable = $this->getTable('eav/entity_attribute');

        return $adapter->fetchCol(
            $adapter->select()
                ->from(['ea' => $entityAttributeTable], [])
                ->join(['a' => $this->getTable('eav/attribute')], 'a.attribute_id = ea.attribute_id', ['frontend_label'])
                ->where('ea.attribute_set_id = ?', $fromSetId)
                ->where(
                    "NOT EXISTS (SELECT 1 FROM {$entityAttributeTable} d"
                    . ' WHERE d.attribute_id = ea.attribute_id AND d.attribute_set_id = ?)',
                    $toSetId,
                )
                ->order('a.frontend_label'),
        );
    }

    /**
     * Return the number of records of set $setId that have a value of $attribute, or null when the
     * records of the entity type have no set or the attribute has no value table.
     */
    public function countRecordsWithValue(int $setId, Mage_Eav_Model_Entity_Attribute_Abstract $attribute): ?int
    {
        if ($attribute->isStatic()) {
            return null;
        }
        $entityType = $attribute->getEntityType();
        $adapter = $this->_getReadAdapter();
        try {
            $entityTable = $this->getTable((string) $entityType->getEntityTable());
            $valueTable = (string) $attribute->getBackendTable();
        } catch (Mage_Core_Exception) {
            return null;
        }
        if (!$adapter->isTableExists($valueTable) || !$adapter->tableColumnExists($entityTable, 'attribute_set_id')) {
            return null;
        }
        $idField = (string) ($entityType->getEntityIdField() ?: 'entity_id');
        return (int) $adapter->fetchOne(
            $adapter->select()
                ->from(['v' => $valueTable], [new Maho\Db\Expr('COUNT(DISTINCT v.entity_id)')])
                ->join(['e' => $entityTable], "e.{$idField} = v.entity_id", [])
                ->where('v.attribute_id = ?', (int) $attribute->getId())
                ->where('e.attribute_set_id = ?', $setId),
        );
    }
}
