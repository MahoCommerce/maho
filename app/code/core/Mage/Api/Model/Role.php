<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

/**
 * @method Mage_Api_Model_Resource_Role _getResource()
 * @method Mage_Api_Model_Resource_Role getResource()
 * @method $this setCreated(string $value)
 * @method $this setModified(string $value)
 *
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_Model_Role extends Mage_Core_Model_Abstract
{
    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/role');
    }

    public function getParentId(): ?int
    {
        return $this->getData('parent_id');
    }

    public function setParentId(?int $value): static
    {
        return $this->setData('parent_id', $value);
    }

    public function getTreeLevel(): ?int
    {
        return $this->getData('tree_level');
    }

    public function setTreeLevel(?int $value): static
    {
        return $this->setData('tree_level', $value);
    }

    public function getSortOrder(): ?int
    {
        return $this->getData('sort_order');
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

    public function getRoleType(): ?string
    {
        return $this->getData('role_type');
    }

    public function setRoleType(?string $value): static
    {
        return $this->setData('role_type', $value);
    }

    public function getUserId(): ?int
    {
        return $this->getData('user_id');
    }

    public function setUserId(?int $value): static
    {
        return $this->setData('user_id', $value);
    }

    public function getRoleName(): ?string
    {
        return $this->getData('role_name');
    }

    public function setRoleName(?string $value): static
    {
        return $this->setData('role_name', $value);
    }
}
