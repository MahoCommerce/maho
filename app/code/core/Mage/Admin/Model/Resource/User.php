<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Model_Resource_User extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('admin/user', 'user_id');
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [
            [
                'field' => 'email',
                'title' => Mage::helper('adminhtml')->__('Email'),
            ],
            [
                'field' => 'username',
                'title' => Mage::helper('adminhtml')->__('User Name'),
            ],
        ];
        return $this;
    }

    /**
     * Authenticate user by $username and $password
     *
     * @return $this
     */
    public function recordLogin(Mage_Admin_Model_User $user)
    {
        $adapter = $this->_getWriteAdapter();

        $data = [
            'logdate' => Mage::app()->getLocale()->formatDateForDb('now'),
            'lognum'  => $user->getLognum() + 1,
        ];

        $condition = [
            'user_id = ?' => (int) $user->getUserId(),
        ];

        $adapter->update($this->getMainTable(), $data, $condition);

        return $this;
    }

    /**
     * Check if user is assigned to any role
     *
     * @param int|Mage_Core_Model_Abstract|Mage_Admin_Model_User $user
     * @return null|array
     */
    public function hasAssigned2Role($user)
    {
        if (is_numeric($user)) {
            $userId = $user;
        } elseif ($user instanceof Mage_Core_Model_Abstract) {
            $userId = $user->getUserId();
        } else {
            return null;
        }

        if ($userId > 0) {
            $adapter = $this->_getReadAdapter();

            $select = $adapter->select();
            $select->from($this->getTable('admin/role'))
                ->where('parent_id > :parent_id')
                ->where('user_id = :user_id');

            $binds = [
                'parent_id' => 0,
                'user_id' => $userId,
            ];

            return $adapter->fetchAll($select, $binds);
        }
        return null;
    }

    /**
     * Set created/modified values before user save
     * @param Mage_Admin_Model_User $user
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $user)
    {
        if ($user->isObjectNew()) {
            $user->setCreated(Mage::app()->getLocale()->formatDateForDb('now'));
        }
        $user->setModified(Mage::app()->getLocale()->formatDateForDb('now'));

        return parent::_beforeSave($user);
    }

    /**
     * Unserialize user extra data after user save
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $user)
    {
        $this->_unserializeExtraData($user);
        return $this;
    }

    /**
     * Unserialize user extra data after user load
     */
    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $user)
    {
        return parent::_afterLoad($this->_unserializeExtraData($user));
    }

    /**
     * Delete user role record with user
     *
     * @return $this
     * @throws Exception
     */
    #[\Override]
    public function delete(Mage_Core_Model_Abstract $user)
    {
        $this->_beforeDelete($user);
        $adapter = $this->_getWriteAdapter();

        $uid = $user->getId();
        $adapter->beginTransaction();
        try {
            $conditions = [
                'user_id = ?' => $uid,
            ];

            $adapter->delete($this->getMainTable(), $conditions);
            $adapter->delete($this->getTable('admin/role'), $conditions);
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
        $this->_afterDelete($user);
        return $this;
    }

    /**
     * @return $this|Mage_Core_Model_Abstract
     */
    public function _saveRelations(Mage_Core_Model_Abstract $user)
    {
        $rolesIds = $user->getRoleIds();
        if (!is_array($rolesIds) || count($rolesIds) == 0) {
            return $user;
        }

        $adapter = $this->_getWriteAdapter();
        $adapter->beginTransaction();

        try {
            $conditions = [
                'user_id = ?' => (int) $user->getId(),
            ];

            $adapter->delete($this->getTable('admin/role'), $conditions);
            foreach ($rolesIds as $rid) {
                $rid = (int) $rid;
                if ($rid > 0) {
                    $role = Mage::getModel('admin/role')->load($rid);
                } else {
                    $role = new \Maho\DataObject(['tree_level' => 0]);
                }

                $data = new \Maho\DataObject([
                    'parent_id'  => $rid,
                    'tree_level' => $role->getTreeLevel() + 1,
                    'sort_order' => 0,
                    'role_type'  => Mage_Admin_Model_Acl::ROLE_TYPE_USER,
                    'user_id'    => $user->getId(),
                    'role_name'  => $user->getFirstname(),
                ]);

                $insertData = $this->_prepareDataForTable($data, $this->getTable('admin/role'));
                $adapter->insert($this->getTable('admin/role'), $insertData);
            }

            if ($user->getId() > 0) {
                // reload acl on next user http request
                $this->saveReloadAclFlag($user, 1);
            }
            $adapter->commit();
        } catch (Mage_Core_Exception|Exception $e) {
            $adapter->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Get user roles
     *
     * @return array
     */
    public function getRoles(Mage_Core_Model_Abstract $user)
    {
        if (!$user->getId()) {
            return [];
        }

        $table   = $this->getTable('admin/role');
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select()
                    ->from($table, [])
                    ->joinLeft(
                        ['ar' => $table],
                        "(ar.role_id = {$table}.parent_id and ar.role_type = 'G')",
                        ['role_id'],
                    )
                    ->where("{$table}.user_id = :user_id");

        $binds = [
            'user_id' => (int) $user->getId(),
        ];

        $roles = $adapter->fetchCol($select, $binds);

        if ($roles) {
            return $roles;
        }

        return [];
    }

    /**
     * Save user roles
     *
     * @return $this
     */
    public function add(Mage_Core_Model_Abstract $user)
    {
        $dbh = $this->_getWriteAdapter();
        $aRoles = $this->hasAssigned2Role($user);
        if (count($aRoles)) {
            foreach ($aRoles as $data) {
                $dbh->delete(
                    $this->getTable('admin/role'),
                    ['role_id = ?' => $data['role_id']],
                );
            }
        }

        if ($user->getId() > 0) {
            $role = Mage::getModel('admin/role')->load($user->getRoleId());
        } else {
            $role = new \Maho\DataObject(['tree_level' => 0]);
        }

        $data = new \Maho\DataObject([
            'parent_id'  => $user->getRoleId(),
            'tree_level' => $role->getTreeLevel() + 1,
            'sort_order' => 0,
            'role_type'  => Mage_Admin_Model_Acl::ROLE_TYPE_USER,
            'user_id'    => $user->getUserId(),
            'role_name'  => $user->getFirstname(),
        ]);

        $insertData = $this->_prepareDataForTable($data, $this->getTable('admin/role'));
        $dbh->insert($this->getTable('admin/role'), $insertData);

        if ($user->getId() > 0) {
            // reload acl on next user http request
            $this->saveReloadAclFlag($user, 1);
        }

        return $this;
    }

    /**
     * Delete user role
     *
     * @return $this
     */
    public function deleteFromRole(Mage_Core_Model_Abstract $user)
    {
        if ($user->getUserId() <= 0) {
            return $this;
        }
        if ($user->getRoleId() <= 0) {
            return $this;
        }

        $dbh = $this->_getWriteAdapter();

        $condition = [
            'user_id = ?'   => (int) $user->getId(),
            'parent_id = ?' => (int) $user->getRoleId(),
        ];

        $dbh->delete($this->getTable('admin/role'), $condition);
        $this->saveReloadAclFlag($user, 1);
        return $this;
    }

    /**
     * Check if role user exists
     *
     * @return array
     */
    public function roleUserExists(Mage_Core_Model_Abstract $user)
    {
        if ($user->getUserId() > 0) {
            $roleTable = $this->getTable('admin/role');

            $dbh = $this->_getReadAdapter();

            $binds = [
                'parent_id' => $user->getRoleId(),
                'user_id'   => $user->getUserId(),
            ];

            $select = $dbh->select()->from($roleTable)
                ->where('parent_id = :parent_id')
                ->where('user_id = :user_id');

            return $dbh->fetchCol($select, $binds);
        }
        return [];
    }

    /**
     * Check if user exists
     *
     * @return array|false
     */
    public function userExists(Mage_Core_Model_Abstract $user)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select();

        $binds = [
            'username' => $user->getUsername(),
            'email'    => $user->getEmail(),
            'user_id'  => (int) $user->getId(),
        ];

        $select->from($this->getMainTable())
            ->where('(username = :username OR email = :email)')
            ->where('user_id <> :user_id');

        return $adapter->fetchRow($select, $binds);
    }

    /**
     * Save user extra data
     *
     * @param Mage_Core_Model_Abstract $object
     * @param string $data
     * @return $this
     */
    public function saveExtra($object, $data)
    {
        if ($object->getId()) {
            $this->_getWriteAdapter()->update(
                $this->getMainTable(),
                ['extra' => $data],
                ['user_id = ?' => (int) $object->getId()],
            );
        }

        return $this;
    }

    /**
     * Make each session of the user build its ACL again on its next request. A $flag that is not 0
     * gives the user a new ACL version (see bumpAclVersion()). A $flag of 0 clears the reload_acl_flag.
     *
     * @param Mage_Core_Model_Abstract $object
     * @param int|bool $flag
     * @return $this
     */
    public function saveReloadAclFlag($object, $flag)
    {
        $userId = (int) $object->getId();
        if ($userId <= 0) {
            return $this;
        }
        if ((int) $flag !== 0) {
            $this->bumpAclVersion([$userId]);
            $this->cleanMenuCache();
        } else {
            $this->_getWriteAdapter()->update(
                $this->getMainTable(),
                ['reload_acl_flag' => 0],
                ['user_id = ?' => $userId],
            );
        }

        return $this;
    }

    /**
     * Add 1 to the acl_version of each user in $userIds, so that each session of these users builds its
     * ACL again (see Mage_Admin_Model_Session::refreshAcl()). The update is one statement, so two
     * concurrent calls do not lose a change. Call cleanMenuCache() after the transaction commits.
     *
     * @param array<int|string> $userIds
     * @return int the number of users that the update changed
     */
    public function bumpAclVersion(array $userIds): int
    {
        $userIds = array_values(array_filter(array_map(intval(...), $userIds), static fn(int $id): bool => $id > 0));
        if ($userIds === []) {
            return 0;
        }

        $adapter = $this->_getWriteAdapter();
        return $adapter->update(
            $this->getMainTable(),
            ['acl_version' => new \Maho\Db\Expr($adapter->quoteIdentifier('acl_version') . ' + 1')],
            ['user_id IN (?)' => $userIds],
        );
    }

    /**
     * Change a reload_acl_flag that is not 0 into a new ACL version for $user. Code that does not
     * know the ACL version sets reload_acl_flag to ask for a reload.
     *
     * @return bool true when this call took the request
     */
    public function takeReloadAclFlag(Mage_Admin_Model_User $user): bool
    {
        $userId = (int) $user->getId();
        if ($userId <= 0) {
            return false;
        }

        $adapter = $this->_getWriteAdapter();
        return $adapter->update(
            $this->getMainTable(),
            [
                'acl_version' => new \Maho\Db\Expr($adapter->quoteIdentifier('acl_version') . ' + 1'),
                'reload_acl_flag' => 0,
            ],
            ['user_id = ?' => $userId, 'reload_acl_flag <> ?' => 0],
        ) > 0;
    }

    /**
     * Read the current acl_version of $user from the database.
     */
    public function getAclVersion(Mage_Admin_Model_User $user): int
    {
        $userId = (int) $user->getId();
        if ($userId <= 0) {
            return 0;
        }

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['acl_version'])
            ->where('user_id = ?', $userId);

        return (int) $adapter->fetchOne($select);
    }

    /**
     * Clean the cache of the admin menu, which depends on the ACL.
     */
    public function cleanMenuCache(): void
    {
        Mage::app()->getCache()->clean([Mage_Adminhtml_Block_Page_Menu::CACHE_TAGS]);
    }

    /**
     * Do not write acl_version, or a reload_acl_flag that did not change, when a loaded user is saved.
     * The value that the request loaded can be old, and writing it back undoes a concurrent change.
     */
    #[\Override]
    protected function _prepareDataForSave(Mage_Core_Model_Abstract $object)
    {
        $data = parent::_prepareDataForSave($object);
        if ((int) $object->getId() > 0) {
            unset($data['acl_version']);
            if (array_key_exists('reload_acl_flag', $data) && !$object->dataHasChangedFor('reload_acl_flag')) {
                unset($data['reload_acl_flag']);
            }
        }

        return $data;
    }

    /**
     * Unserializes user extra data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _unserializeExtraData(Mage_Core_Model_Abstract $user)
    {
        try {
            $extra = Mage::helper('core/unserializeArray')->unserialize($user->getExtra());
            // Rows saved through the old loadByUsername() path hold a JSON string inside JSON
            while (is_string($extra) && json_validate($extra)) {
                $extra = Mage::helper('core')->jsonDecode($extra);
            }
            $user->setExtra($extra);
        } catch (\Throwable) {
            // Catch Throwable, not Exception — PHP 8's TypeError extends
            // Error (not Exception), and the helper called above can throw
            // TypeError (json_validate requires string) on a re-load where
            // setExtra(array) was already called by a previous pass.
            $user->setExtra(false);
        }
        return $user;
    }
}
