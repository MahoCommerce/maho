<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Resource_Group extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/customer_group', 'customer_group_id');
    }

    /**
     * Initialize unique fields
     *
     * @return $this
     */
    #[\Override]
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [
            [
                'field' => 'customer_group_code',
                'title' => Mage::helper('customer')->__('Customer Group'),
            ]];

        return $this;
    }

    /**
     * Refuse to delete the NOT LOGGED IN group, which guests use, and a group that a scope uses as the default group
     *
     * @throws Mage_Core_Exception
     */
    public function validateDelete(Mage_Customer_Model_Group $group): void
    {
        if ((int) $group->getId() === Mage_Customer_Model_Group::NOT_LOGGED_IN_ID || $group->usesAsDefault()) {
            Mage::throwException(Mage::helper('customer')->__('The group "%s" cannot be deleted', $group->getCode()));
        }
    }

    /**
     * Count the rows that refer to the group through a foreign key, for each table and column
     *
     * @return list<array{table: string, column: string, on_delete: string, rows: int}>
     */
    public function getReferencingRows(int $groupId): array
    {
        $adapter = $this->getReadConnection();
        $result = [];
        foreach ($adapter->listTables() as $table) {
            foreach ($adapter->getForeignKeys($table) as $key) {
                if ($key['REF_TABLE_NAME'] !== $this->getMainTable()) {
                    continue;
                }
                $column = $key['COLUMN_NAME'];
                $select = $adapter->select()
                    ->from($table, [new Maho\Db\Expr('COUNT(*)')])
                    ->where($adapter->quoteIdentifier($column) . ' = ?', $groupId);
                $rows = (int) $adapter->fetchOne($select);
                if ($rows) {
                    $result[] = ['table' => $table, 'column' => $column, 'on_delete' => (string) $key['ON_DELETE'], 'rows' => $rows];
                }
            }
        }
        return $result;
    }

    /**
     * @throws Mage_Core_Exception
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    #[\Override]
    protected function _beforeDelete(Mage_Core_Model_Abstract $group)
    {
        /** @var Mage_Customer_Model_Group $group */
        $this->validateDelete($group);
        return parent::_beforeDelete($group);
    }

    /**
     * Method set default group id to the customers collection
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $group)
    {
        $customerCollection = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToFilter('group_id', $group->getId())
            ->load();
        /** @var Mage_Customer_Model_Customer $customer */
        foreach ($customerCollection as $customer) {
            $defaultGroupId = Mage::helper('customer')->getDefaultCustomerGroupId($customer->getStoreId());
            $customer->setGroupId($defaultGroupId);
            $customer->save();
        }
        return parent::_afterDelete($group);
    }
}
