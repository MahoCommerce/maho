<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Resource_User extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/user', 'user_id');
    }

    #[\Override]
    protected function _initUniqueFields(): static
    {
        $this->_uniqueFields = [
            ['field' => 'email', 'title' => Mage::helper('apiplatform')->__('Email')],
            ['field' => 'username', 'title' => Mage::helper('apiplatform')->__('Username')],
        ];
        return $this;
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): static
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if (!$object->getId()) {
            $object->setData('created', $now);
        }
        $object->setData('modified', $now);

        // A loaded user carries the stored hash; only a changed, non-empty value is a new plain key
        $apiKey = $object->getData('api_key');
        if (is_string($apiKey) && $apiKey !== '' && $apiKey !== $object->getOrigData('api_key')) {
            $object->setData('api_key', Mage::helper('core')->getHashPassword($apiKey));
        }

        return $this;
    }

    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $object): static
    {
        $this->_getWriteAdapter()->delete(
            $this->getTable('apiplatform/role'),
            ['user_id = ?' => (int) $object->getId()],
        );
        return $this;
    }

    /**
     * @return list<int>
     */
    public function getRoleIds(int $userId): array
    {
        $adapter = $this->_getReadAdapter();
        $roleTable = $this->getTable('apiplatform/role');
        $select = $adapter->select()
            ->from(['assignment' => $roleTable], [])
            ->join(
                ['role' => $roleTable],
                $adapter->quoteInto('role.role_id = assignment.parent_id AND role.role_type = ?', Maho_ApiPlatform_Model_User::ROLE_TYPE_GROUP),
                ['role_id'],
            )
            ->where('assignment.user_id = ?', $userId)
            ->where('assignment.role_type = ?', Maho_ApiPlatform_Model_User::ROLE_TYPE_USER);

        return array_map(intval(...), $adapter->fetchCol($select));
    }

    public function assignRole(Maho_ApiPlatform_Model_User $user, int $roleId): void
    {
        $adapter = $this->_getWriteAdapter();
        $roleTable = $this->getTable('apiplatform/role');
        $userId = (int) $user->getId();

        $adapter->beginTransaction();
        try {
            $adapter->delete($roleTable, [
                'user_id = ?' => $userId,
                'role_type = ?' => Maho_ApiPlatform_Model_User::ROLE_TYPE_USER,
            ]);
            if ($roleId > 0) {
                $adapter->insert($roleTable, [
                    'parent_id'  => $roleId,
                    'tree_level' => 2,
                    'sort_order' => 0,
                    'role_type'  => Maho_ApiPlatform_Model_User::ROLE_TYPE_USER,
                    'user_id'    => $userId,
                    'role_name'  => $user->getUsername(),
                ]);
            }
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
    }
}
