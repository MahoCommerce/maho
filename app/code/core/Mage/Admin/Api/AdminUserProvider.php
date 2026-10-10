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

final class AdminUserProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['username' => 'ASC'];

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $search = $this->stringFilter($filters, 'search') ?? $this->stringFilter($filters, 'q');
        if ($search !== null) {
            $like = ['like' => "%{$search}%"];
            $collection->addFieldToFilter(
                ['username', 'firstname', 'lastname', 'email'],
                [$like, $like, $like, $like],
            );
        }

        $isActive = $this->booleanFilter($filters, 'isActive');
        if ($isActive !== null) {
            $collection->addFieldToFilter('is_active', $isActive ? 1 : 0);
        }
    }

    /**
     * Add the role of the user. A user without a role has null in both fields.
     */
    #[\Override]
    protected function afterMap(Resource $dto, object $model): void
    {
        if (!$dto instanceof AdminUser || !$model instanceof \Mage_Admin_Model_User) {
            return;
        }

        $role = $model->getRole();
        $dto->roleId = $role->getId() ? (int) $role->getId() : null;
        $dto->roleName = $role->getId() ? (string) $role->getRoleName() : null;
    }
}
