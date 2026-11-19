<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Model_Resource_Roles extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Users table
     *
     * @var string
     */
    protected $_usersTable;

    /**
     * Rule table
     *
     * @var string
     */
    protected $_ruleTable;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('admin/role', 'role_id');

        $this->_usersTable = $this->getTable('admin/user');
        $this->_ruleTable  = $this->getTable('admin/rule');
    }

    /**
     * Process role before saving
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $role)
    {
        if ($role->getId() == '') {
            if ($role->getIdFieldName()) {
                $role->unsetData($role->getIdFieldName());
            } else {
                $role->unsetData('id');
            }
        }

        if ($role->getPid() > 0) {
            $select = $this->_getReadAdapter()->select()
                ->from($this->getMainTable(), ['tree_level'])
                ->where("{$this->getIdFieldName()} = :pid");

            $binds = [
                'pid' => (int) $role->getPid(),
            ];

            $treeLevel = (int) $this->_getReadAdapter()->fetchOne($select, $binds);
        } else {
            $treeLevel = 0;
        }

        $role->setTreeLevel($treeLevel + 1);
        $role->setRoleName($role->getName());

        return $this;
    }

    /**
     * Process role after saving
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $role)
    {
        $this->_updateRoleUsersAcl($role);
        $this->getUserResource()->cleanMenuCache();
        return $this;
    }

    /**
     * Process role after deleting
     *
     * @return $this
     */
    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $role)
    {
        // Read the members before their rows are deleted, so that their sessions lose the role
        $users = $role instanceof Mage_Admin_Model_Roles ? $this->getRoleUsers($role) : [];

        $adapter = $this->_getWriteAdapter();
        $adapter->delete($this->getMainTable(), ['parent_id = ?' => (int) $role->getId()]);
        $adapter->delete($this->_ruleTable, ['role_id = ?' => (int) $role->getId()]);

        if ($this->getUserResource()->bumpAclVersion($users) > 0) {
            $this->getUserResource()->cleanMenuCache();
        }
        return $this;
    }

    /**
     * Get role users
     *
     * @return array
     */
    public function getRoleUsers(Mage_Admin_Model_Roles $role)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select()
            ->from($this->getMainTable(), ['user_id'])
            ->where('parent_id = ?', $role->getId())
            ->where('role_type = ?', Mage_Admin_Model_Acl::ROLE_TYPE_USER)
            ->where('user_id > 0');
        return $adapter->fetchCol($select);
    }

    /**
     * Give each member of $role a new ACL version.
     */
    private function _updateRoleUsersAcl(Mage_Admin_Model_Roles $role): bool
    {
        return $this->getUserResource()->bumpAclVersion($this->getRoleUsers($role)) > 0;
    }

    protected function getUserResource(): Mage_Admin_Model_Resource_User
    {
        return Mage::getResourceSingleton('admin/user');
    }
}
