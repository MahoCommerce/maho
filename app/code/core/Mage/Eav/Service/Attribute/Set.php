<?php

/**
 * Load, create, rename and delete the attribute sets of every entity type, and change their groups and attributes,
 * for the admin pages, the API and the importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

use Mage_Eav_Model_Entity_Attribute_Group as Group;
use Mage_Eav_Model_Entity_Attribute_Set as Set;

class Mage_Eav_Service_Attribute_Set
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity when the set does not exist or is a set of another entity type
     */
    public function getById(int $id, string $entityTypeCode): Set
    {
        if ($this->getSetEntityTypeId($id) !== (int) $this->getEntityType($entityTypeCode)->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('eav')->__('Attribute set not found'));
        }
        /** @var Set $set */
        $set = Mage::getModel('eav/entity_attribute_set');
        return $set->load($id);
    }

    /**
     * Return a new set of the entity type $entityTypeCode. The transport gives it a name, then calls create().
     */
    public function newSet(string $entityTypeCode): Set
    {
        /** @var Set $set */
        $set = Mage::getModel('eav/entity_attribute_set');
        return $set->setEntityTypeId((int) $this->getEntityType($entityTypeCode)->getId());
    }

    /**
     * Save the new set $set with a copy of the groups and the attributes of the set $skeletonId. Without
     * $skeletonId, the copy comes from the default set of the entity type. A failed copy saves nothing.
     *
     * @throws Mage_Core_Exception_Input when the name is empty or too long, or $skeletonId is not a set of the entity type
     * @throws Mage_Core_Exception_Conflict when another set has the name, or every record of the entity type uses one set
     */
    public function create(Set $set, ?int $skeletonId = null): Set
    {
        $helper = Mage::helper('eav');
        $entityType = Mage::getSingleton('eav/config')->getEntityType((int) $set->getEntityTypeId());
        if ($helper->getAdminPageSettings($entityType)?->singleSet) {
            throw new Mage_Core_Exception_Conflict($helper->__('This entity type has one attribute set.'));
        }
        $skeletonId ??= (int) $entityType->getDefaultAttributeSetId();
        if ($this->getSetEntityTypeId($skeletonId) !== (int) $entityType->getId()) {
            new Mage_Core_Exception_Input()
                ->addError('skeleton_id', $helper->__('The skeleton must be an attribute set of the same entity type.'))
                ->throwIfErrors();
        }

        $adapter = $this->getWriteAdapter();
        $adapter->beginTransaction();
        try {
            $set->save();
            $set->initFromSkeleton($skeletonId)->save();
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            $set->setId(null);
            throw $e;
        }
        $this->clearCache();
        return $set;
    }

    /**
     * Save $set, for example after a rename. The resource model checks the name.
     *
     * @throws Mage_Core_Exception_Input when the name is empty or too long
     * @throws Mage_Core_Exception_Conflict when another set of the entity type has the name
     */
    public function save(Set $set): Set
    {
        $set->save();
        $this->clearCache();
        return $set;
    }

    /**
     * Delete $set. The records of the set move to the default set, unless a foreign key deletes them with the
     * set, as for products.
     *
     * @throws Mage_Core_Exception_Conflict for the default set of the entity type
     */
    public function delete(Set $set): void
    {
        $adapter = $this->getWriteAdapter();
        $adapter->beginTransaction();
        try {
            $this->moveRecordsToDefaultSet($set);
            $set->delete();
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
        $this->clearCache();
    }

    /**
     * Add the new group $group to $set. A group with no sort order goes after the last group.
     *
     * @throws Mage_Core_Exception_Input when the name is empty or too long, or the sort order is negative
     * @throws Mage_Core_Exception_Conflict when another group of the set has the name
     */
    public function addGroup(Set $set, Group $group): Group
    {
        $group->setId(null)->setAttributeSetId((int) $set->getId())->save();
        $this->clearCache();
        return $group;
    }

    /**
     * Put an attribute into a group of $set, or move it to that group. $attribute is the ID or the code of an
     * attribute of the entity type. $group is the ID or the name of a group of the set. A sort order of 0 puts
     * the attribute after the last attribute of the group.
     *
     * @throws Mage_Core_Exception_Input with one error for each value that names nothing
     */
    public function assignAttribute(Set $set, int|string|null $attribute, int|string|null $group, int $sortOrder = 0): void
    {
        $errors = new Mage_Core_Exception_Input();
        $attributeId = $this->findAttributeId($set, $attribute, $errors);
        $groupId = $this->findGroupId($set, $group, $errors);
        if ($sortOrder < 0) {
            $errors->addError('sort_order', Mage::helper('eav')->__('The sort order must be 0 or more.'));
        }
        $errors->throwIfErrors();

        /** @var Mage_Eav_Model_Entity_Attribute $row */
        $row = Mage::getModel('eav/entity_attribute');
        $row->setId($attributeId);
        $row->setEntityTypeId((int) $set->getEntityTypeId())
            ->setAttributeSetId((int) $set->getId())
            ->setAttributeGroupId($groupId)
            ->setSortOrder($sortOrder);
        $row->getResource()->saveInSetIncluding($row);
        $this->clearCache();
    }

    /**
     * Take the attribute $attributeId out of $set.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the attribute is not in the set
     * @throws Mage_Core_Exception_Conflict for a system attribute, and for an attribute that configurable products of the set use
     */
    public function unassignAttribute(Set $set, int $attributeId): void
    {
        $rows = $this->getSetRows($set, $attributeId);
        if ($rows === []) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('eav')->__('The attribute is not in this attribute set.'));
        }
        $this->checkRemoval($set, $rows);

        /** @var Mage_Eav_Model_Entity_Attribute $row */
        $row = Mage::getModel('eav/entity_attribute');
        $row->setEntityAttributeId($rows[0]['entity_attribute_id'])->deleteEntity();
        $this->clearCache();
    }

    /**
     * Save the tree of the set editor into $set in one save: the name, the groups, the attributes of each group,
     * the attributes that leave the set and the removed groups. The tree loses each group and each attribute
     * that belongs to another set or another entity type.
     *
     * @param array<string, mixed> $tree the keys attribute_set_name, groups, attributes, not_attributes and removeGroups
     * @throws Mage_Core_Exception_Conflict when an attribute that leaves the set is a system attribute, or configurable products use it
     * @throws Mage_Core_Exception_Input when a name is empty or too long
     */
    public function saveTree(Set $set, array $tree): Set
    {
        $tree = $this->filterTree($set, $tree);
        $this->checkRemoval($set, $this->getLeavingRows($set, $tree));
        $set->organizeData($tree);
        $set->save();
        $this->clearCache();
        return $set;
    }

    /**
     * Return the attributes that the tree of the set editor takes out of $set: the removed attributes, and the
     * attributes of a removed group that no other group holds now.
     *
     * @param array<string, mixed> $tree
     * @return list<Mage_Eav_Model_Entity_Attribute_Abstract>
     * @throws Mage_Core_Exception_Conflict when one of them is a system attribute, or configurable products use it
     */
    public function getRemovedAttributes(Set $set, array $tree): array
    {
        $rows = $this->getLeavingRows($set, $this->filterTree($set, $tree));
        $this->checkRemoval($set, $rows);

        $attributes = [];
        foreach ($rows as $row) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute((int) $set->getEntityTypeId(), $row['attribute_id']);
            if ($attribute && $attribute->getId()) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * Refuse to take a system attribute out of $set, because the nightly eav_clean_orphaned_records job deletes
     * the values of an attribute that is not in the set. Refuse an attribute that configurable products of the set use.
     *
     * @param list<array{entity_attribute_id: int, attribute_id: int, attribute_group_id: int, is_user_defined: bool, in_removed_group: bool}> $rows
     * @throws Mage_Core_Exception_Conflict
     */
    protected function checkRemoval(Set $set, array $rows): void
    {
        $helper = Mage::helper('eav');
        foreach ($rows as $row) {
            if (!$row['is_user_defined']) {
                throw new Mage_Core_Exception_Conflict($row['in_removed_group']
                    ? $helper->__('Cannot delete group. Please move system attributes to another group and try again.')
                    : $helper->__('A system attribute cannot be removed from an attribute set.'));
            }
            $attribute = Mage::getSingleton('eav/config')->getAttribute((int) $set->getEntityTypeId(), $row['attribute_id']);
            $resource = $attribute ? $attribute->getResource() : null;
            // Only the resource of the catalog attributes knows the configurable products
            if ($resource && method_exists($resource, 'isUsedBySuperProducts') && $resource->isUsedBySuperProducts($attribute, (int) $set->getId())) {
                throw new Mage_Core_Exception_Conflict($helper->__("Attribute '%s' used in configurable products", $attribute->getAttributeCode()));
            }
        }
    }

    /**
     * Move the records of $set to the default set of its entity type. Do nothing when a foreign key deletes the
     * records with the set.
     */
    protected function moveRecordsToDefaultSet(Set $set): void
    {
        $entityType = Mage::getSingleton('eav/config')->getEntityType((int) $set->getEntityTypeId());
        if (!$entityType->getEntityTable()) {
            return;
        }
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName((string) $entityType->getEntityTable());
        $adapter = $this->getWriteAdapter();
        if (!$adapter->tableColumnExists($table, 'attribute_set_id')) {
            return;
        }
        foreach ($adapter->getForeignKeys($table) as $key) {
            if ($key['COLUMN_NAME'] === 'attribute_set_id' && $key['ON_DELETE'] === 'CASCADE') {
                return;
            }
        }
        $adapter->update(
            $table,
            ['attribute_set_id' => (int) $entityType->getDefaultAttributeSetId()],
            ['attribute_set_id = ?' => (int) $set->getId()],
        );
    }

    /**
     * Remove from $tree the groups and the attributes of other sets and of other entity types.
     *
     * @param array<string, mixed> $tree
     * @return array{attribute_set_name: string, groups: list<mixed>, attributes: list<mixed>, not_attributes: list<mixed>, removeGroups: list<mixed>}
     */
    private function filterTree(Set $set, array $tree): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $ids = fn(string $table, string $column, string $field, int $value): array => array_map(intval(...), $adapter->fetchCol(
            $adapter->select()->from($resource->getTableName($table), [$column])->where("$field = ?", $value),
        ));
        $groupIds = $ids('eav/attribute_group', 'attribute_group_id', 'attribute_set_id', (int) $set->getId());
        $rowIds = $ids('eav/entity_attribute', 'entity_attribute_id', 'attribute_set_id', (int) $set->getId());
        $attributeIds = $ids('eav/attribute', 'attribute_id', 'entity_type_id', (int) $set->getEntityTypeId());
        $keep = fn(string $key, callable $check): array => array_values(array_filter((array) ($tree[$key] ?? []), $check));

        return [
            'attribute_set_name' => (string) ($tree['attribute_set_name'] ?? $set->getAttributeSetName()),
            // A group id that is not a number is a group that the editor added
            'groups' => $keep('groups', fn($group) => !is_numeric($group[0] ?? null) || in_array((int) $group[0], $groupIds, true)),
            'attributes' => $keep('attributes', fn($attribute) => in_array((int) ($attribute[0] ?? 0), $attributeIds, true)),
            'not_attributes' => $keep('not_attributes', fn($id) => in_array((int) $id, $rowIds, true)),
            'removeGroups' => $keep('removeGroups', fn($id) => in_array((int) $id, $groupIds, true)),
        ];
    }

    /**
     * Return the rows of the attributes that $tree takes out of $set: a removed row, or a row of a removed group,
     * whose attribute the tree does not put into another group.
     *
     * @param array{attribute_set_name: string, groups: list<mixed>, attributes: list<mixed>, not_attributes: list<mixed>, removeGroups: list<mixed>} $tree
     * @return list<array{entity_attribute_id: int, attribute_id: int, attribute_group_id: int, is_user_defined: bool, in_removed_group: bool}>
     */
    private function getLeavingRows(Set $set, array $tree): array
    {
        $removedRows = array_map(intval(...), $tree['not_attributes']);
        $removedGroups = array_map(intval(...), $tree['removeGroups']);
        $postedAttributes = array_map(fn($attribute) => (int) $attribute[0], $tree['attributes']);

        $rows = [];
        foreach ($this->getSetRows($set) as $row) {
            $inRemovedGroup = in_array($row['attribute_group_id'], $removedGroups, true);
            if (in_array($row['attribute_id'], $postedAttributes, true)
                || (!$inRemovedGroup && !in_array($row['entity_attribute_id'], $removedRows, true))
            ) {
                continue;
            }
            $rows[] = ['in_removed_group' => $inRemovedGroup] + $row;
        }
        return $rows;
    }

    /**
     * Return the attributes of $set, or the attribute $attributeId only, with their places in the set.
     *
     * @return list<array{entity_attribute_id: int, attribute_id: int, attribute_group_id: int, is_user_defined: bool, in_removed_group: bool}>
     */
    private function getSetRows(Set $set, ?int $attributeId = null): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from(['ea' => $resource->getTableName('eav/entity_attribute')], ['entity_attribute_id', 'attribute_id', 'attribute_group_id'])
            ->join(['a' => $resource->getTableName('eav/attribute')], 'a.attribute_id = ea.attribute_id', ['is_user_defined'])
            ->where('ea.attribute_set_id = ?', (int) $set->getId());
        if ($attributeId !== null) {
            // An integer literal: PostgreSQL refuses a quoted value above the range of the SMALLINT column
            $select->where('ea.attribute_id = ' . $attributeId);
        }
        return array_map(fn(array $row): array => [
            'entity_attribute_id' => (int) $row['entity_attribute_id'],
            'attribute_id' => (int) $row['attribute_id'],
            'attribute_group_id' => (int) $row['attribute_group_id'],
            'is_user_defined' => (bool) $row['is_user_defined'],
            'in_removed_group' => false,
        ], $adapter->fetchAll($select));
    }

    /**
     * Return the ID of the attribute $attribute, an ID or a code, of the entity type of $set. Add an error to
     * $errors and return 0 when no attribute matches.
     */
    private function findAttributeId(Set $set, int|string|null $attribute, Mage_Core_Exception_Input $errors): int
    {
        $helper = Mage::helper('eav');
        if ($attribute === null || $attribute === '') {
            $errors->addError('attribute_id', $helper->__('An attribute ID or an attribute code is required.'));
            return 0;
        }
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('eav/attribute'), ['attribute_id'])
            ->where('entity_type_id = ?', (int) $set->getEntityTypeId());
        // An integer literal: PostgreSQL refuses a quoted value above the range of the SMALLINT column
        is_int($attribute) ? $select->where('attribute_id = ' . $attribute) : $select->where('attribute_code = ?', $attribute);
        $id = (int) $adapter->fetchOne($select);
        if ($id === 0) {
            is_int($attribute)
                ? $errors->addError('attribute_id', $helper->__('No attribute of this entity type has the ID %s.', $attribute))
                : $errors->addError('attribute_code', $helper->__('No attribute of this entity type has the code "%s".', $attribute));
        }
        return $id;
    }

    /**
     * Return the ID of the group $group, an ID or a name, of $set. Add an error to $errors and return 0 when no
     * group matches.
     */
    private function findGroupId(Set $set, int|string|null $group, Mage_Core_Exception_Input $errors): int
    {
        $helper = Mage::helper('eav');
        $group = is_string($group) ? trim($group) : $group;
        if ($group === null || $group === '') {
            $errors->addError('group_id', $helper->__('A group ID or a group name is required.'));
            return 0;
        }
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('eav/attribute_group'), ['attribute_group_id'])
            ->where('attribute_set_id = ?', (int) $set->getId());
        // An integer literal: PostgreSQL refuses a quoted value above the range of the SMALLINT column
        is_int($group) ? $select->where('attribute_group_id = ' . $group) : $select->where('attribute_group_name = ?', $group);
        $id = (int) $adapter->fetchOne($select);
        if ($id === 0) {
            is_int($group)
                ? $errors->addError('group_id', $helper->__('No group of this attribute set has the ID %s.', $group))
                : $errors->addError('group_name', $helper->__('No group of this attribute set has the name "%s".', $group));
        }
        return $id;
    }

    /**
     * Return the entity type of the set $setId, or null when the set does not exist.
     */
    private function getSetEntityTypeId(int $setId): ?int
    {
        if ($setId <= 0) {
            return null;
        }
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        // An integer literal: PostgreSQL refuses a quoted value above the range of the SMALLINT column
        $value = $adapter->fetchOne(
            $adapter->select()
                ->from($resource->getTableName('eav/attribute_set'), ['entity_type_id'])
                ->where('attribute_set_id = ' . $setId),
        );
        return $value === false || $value === null ? null : (int) $value;
    }

    private function getEntityType(string $entityTypeCode): Mage_Eav_Model_Entity_Type
    {
        return Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
    }

    private function getWriteAdapter(): Maho\Db\Adapter\AdapterInterface
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    private function clearCache(): void
    {
        Mage::getSingleton('eav/config')->clear();
        Mage::app()->cleanCache([Mage_Eav_Model_Entity_Attribute::CACHE_TAG]);
    }
}
