<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

namespace Mage\Admin\Api;

use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;

final class AdminRoleProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['role_name' => 'ASC'];

    /**
     * The role table also holds one row per user assignment (role_type U), so only group roles are roles here.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?AdminRole
    {
        /** @var \Mage_Admin_Model_Role $role */
        $role = $this->loadById('admin/role', $id);
        if (!$role->getId() || $role->getRoleType() !== \Mage_Admin_Model_Acl::ROLE_TYPE_GROUP) {
            return null;
        }

        /** @var AdminRole */
        return $this->toDto($role);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addFieldToFilter('role_type', \Mage_Admin_Model_Acl::ROLE_TYPE_GROUP);

        $search = $this->stringFilter($filters, 'search') ?? $this->stringFilter($filters, 'q');
        if ($search !== null) {
            $collection->addFieldToFilter('role_name', ['like' => "%{$search}%"]);
        }
    }

    /**
     * Add the allowed ACL resources and the number of users of the role.
     */
    #[\Override]
    protected function afterMap(Resource $dto, object $model): void
    {
        if (!$dto instanceof AdminRole) {
            return;
        }

        $roleId = (int) $model->getId();
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $select = $adapter->select()
            ->from($resource->getTableName('admin/rule'), ['resource_id'])
            ->where('role_id = ?', $roleId)
            ->where('permission = ?', \Mage_Admin_Model_Rules::RULE_PERMISSION_ALLOWED)
            ->order('resource_id ASC');
        $allowed = array_map(strval(...), $adapter->fetchCol($select));
        $dto->resources = in_array('all', $allowed, true) ? ['all'] : array_values(array_filter(array_map(
            static fn(string $id): ?string => str_starts_with($id, 'admin/') ? substr($id, 6) : null,
            $allowed,
        )));

        $select = $adapter->select()
            ->from($resource->getTableName('admin/role'), ['count' => new \Maho\Db\Expr('COUNT(*)')])
            ->where('parent_id = ?', $roleId)
            ->where('role_type = ?', \Mage_Admin_Model_Acl::ROLE_TYPE_USER);
        $dto->userCount = (int) $adapter->fetchOne($select);
    }
}
