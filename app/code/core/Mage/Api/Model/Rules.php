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
 * @method Mage_Api_Model_Resource_Rules _getResource()
 * @method Mage_Api_Model_Resource_Rules getResource()
 *
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_Model_Rules extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/rules');
    }

    /**
     * @return $this
     */
    public function update()
    {
        $this->getResource()->update($this);
        return $this;
    }

    /**
     * @return Mage_Api_Model_Resource_Permissions_Collection
     */
    #[\Override]
    public function getCollection()
    {
        return Mage::getResourceModel('api/permissions_collection');
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function saveRel()
    {
        $this->getResource()->saveRel($this);
        return $this;
    }

    public function getAssertId(): ?int
    {
        $value = $this->getData('assert_id');
        return $value === null ? null : (int) $value;
    }

    public function setAssertId(?int $value): static
    {
        return $this->setData('assert_id', $value);
    }

    public function getPermission(): ?string
    {
        $value = $this->getData('permission');
        return $value === null ? null : (string) $value;
    }

    public function setPermission(?string $value): static
    {
        return $this->setData('permission', $value);
    }

    public function getPrivileges(): ?string
    {
        $value = $this->getData('privileges');
        return $value === null ? null : (string) $value;
    }

    public function setPrivileges(?string $value): static
    {
        return $this->setData('privileges', $value);
    }

    public function getResourceId(): ?string
    {
        $value = $this->getData('resource_id');
        return $value === null ? null : (string) $value;
    }

    public function setResourceId(?string $value): static
    {
        return $this->setData('resource_id', $value);
    }

    public function getRoleId(): ?int
    {
        $value = $this->getData('role_id');
        return $value === null ? null : (int) $value;
    }

    public function setRoleId(?int $value): static
    {
        return $this->setData('role_id', $value);
    }

    public function getRoleType(): ?string
    {
        $value = $this->getData('role_type');
        return $value === null ? null : (string) $value;
    }

    public function setRoleType(?string $value): static
    {
        return $this->setData('role_type', $value);
    }

}
