<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Entity_Attribute_Group extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/attribute_group', 'attribute_group_id');
    }

    /**
     * Checks if attribute group exists
     *
     * @param Mage_Eav_Model_Entity_Attribute_Group $object
     * @return bool
     */
    public function itemExists($object)
    {
        $adapter   = $this->_getReadAdapter();
        $bind      = [
            'attribute_set_id'      => $object->getAttributeSetId(),
            'attribute_group_name'  => $object->getAttributeGroupName(),
        ];
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('attribute_set_id = :attribute_set_id')
            ->where('attribute_group_name = :attribute_group_name');

        return (bool) $adapter->fetchRow($select, $bind);
    }

    /**
     * Strip the tags from the name, and refuse an empty name, a name of more than 255 characters, a negative sort
     * order and the name of another group of the set. A group with no sort order goes after the last group.
     *
     * @param Mage_Eav_Model_Entity_Attribute_Group $object
     * @return Mage_Core_Model_Resource_Db_Abstract
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $helper = Mage::helper('eav');
        $name = trim(strip_tags((string) $object->getAttributeGroupName()));
        $object->setAttributeGroupName($name);
        $errors = new Mage_Core_Exception_Input();
        if ($name === '' || mb_strlen($name) > 255) {
            $errors->addError('attribute_group_name', $helper->__('The name of a group must have 1 to 255 characters.'));
        }
        if ((int) $object->getSortOrder() < 0) {
            $errors->addError('sort_order', $helper->__('The sort order must be 0 or more.'));
        }
        $errors->throwIfErrors();

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), [$this->getIdFieldName()])
            ->where('attribute_set_id = ?', (int) $object->getAttributeSetId())
            ->where('attribute_group_name = ?', $name);
        if ($object->getId()) {
            $select->where($this->getIdFieldName() . ' != ?', (int) $object->getId());
        }
        if ($adapter->fetchOne($select)) {
            throw new Mage_Core_Exception_Conflict($helper->__('Attribute group with the "%s" name already exists', $name));
        }

        if (!$object->getSortOrder()) {
            $object->setSortOrder($this->_getMaxSortOrder($object) + 1);
        }
        return parent::_beforeSave($object);
    }

    /**
     * Perform actions after object save
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        if ($object->getAttributes()) {
            foreach ($object->getAttributes() as $attribute) {
                // Write only the place in the set: a full save writes the sort order of the set into the table of
                // the attribute, and customer_eav_attribute.sort_order gives the order of the customer forms
                $attribute->setAttributeGroupId($object->getId());
                $attribute->getResource()->saveInSetIncluding($attribute);
            }
        }
        if (is_array($object->getData('store_labels'))) {
            $this->saveStoreLabels((int) $object->getId(), $object->getData('store_labels'));
        }

        return parent::_afterSave($object);
    }

    /**
     * Return the labels of group $groupId for each store view, by store id.
     *
     * @return array<int, string>
     */
    public function getStoreLabels(int $groupId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('eav/attribute_group_label'), ['store_id', 'value'])
            ->where('attribute_group_id = ?', $groupId);
        $labels = [];
        foreach ($adapter->fetchPairs($select) as $storeId => $value) {
            $labels[(int) $storeId] = (string) $value;
        }
        return $labels;
    }

    /**
     * Replace the labels of group $groupId. A label is plain text. An empty label, the admin store
     * and a store that does not exist are left out.
     *
     * @param array<int|string, mixed> $labels
     */
    public function saveStoreLabels(int $groupId, array $labels): static
    {
        $storeIds = array_map(intval(...), array_keys(Mage::app()->getStores()));
        $rows = [];
        foreach ($labels as $storeId => $value) {
            $value = mb_substr(trim(strip_tags((string) $value)), 0, 255);
            if ($value !== '' && in_array((int) $storeId, $storeIds, true)) {
                $rows[] = ['attribute_group_id' => $groupId, 'store_id' => (int) $storeId, 'value' => $value];
            }
        }

        $adapter = $this->_getWriteAdapter();
        $table = $this->getTable('eav/attribute_group_label');
        $adapter->delete($table, ['attribute_group_id = ?' => $groupId]);
        if ($rows) {
            $adapter->insertMultiple($table, $rows);
        }
        return $this;
    }

    /**
     * Retrieve max sort order
     *
     * @param Mage_Core_Model_Abstract|Mage_Eav_Model_Entity_Attribute_Group $object
     * @return false|string|null
     */
    protected function _getMaxSortOrder($object)
    {
        $adapter = $this->_getReadAdapter();
        $bind    = [':attribute_set_id' => $object->getAttributeSetId()];
        $select  = $adapter->select()
            ->from($this->getMainTable(), new Maho\Db\Expr('MAX(sort_order)'))
            ->where('attribute_set_id = :attribute_set_id');

        return $adapter->fetchOne($select, $bind);
    }

    /**
     * Set any group default if old one was removed
     *
     * @param int $attributeSetId
     * @return $this
     */
    public function updateDefaultGroup($attributeSetId)
    {
        $adapter = $this->_getWriteAdapter();
        $bind    = [':attribute_set_id' => $attributeSetId];
        $select  = $adapter->select()
            ->from($this->getMainTable(), $this->getIdFieldName())
            ->where('attribute_set_id = :attribute_set_id')
            ->order('default_id ' . \Maho\Data\Collection::SORT_ORDER_DESC)
            ->limit(1);

        $groupId = $adapter->fetchOne($select, $bind);

        if ($groupId) {
            $data  = ['default_id' => 1];
            $where = ['attribute_group_id =?' => $groupId];
            $adapter->update($this->getMainTable(), $data, $where);
        }

        return $this;
    }
}
