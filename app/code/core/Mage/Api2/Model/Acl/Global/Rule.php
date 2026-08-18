<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

declare(strict_types=1);

/**
 * API2 Global ACL Rule model
 *
 * @package    Mage_Api2
 *
 * @method Mage_Api2_Model_Resource_Acl_Global_Rule_Collection getCollection()
 * @method Mage_Api2_Model_Resource_Acl_Global_Rule_Collection getResourceCollection()
 * @method Mage_Api2_Model_Resource_Acl_Global_Rule getResource()
 * @method Mage_Api2_Model_Resource_Acl_Global_Rule _getResource()
 * @method int getPermission()
 * @method $this setPermission(int $permission)
 * @method string getAllowedAttributes()
 * @method $this setAllowedAttributes(string $allowedAttributes)
 *
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api2_Model_Acl_Global_Rule extends Mage_Core_Model_Abstract
{
    /**
     * Root resource ID "all"
     */
    public const RESOURCE_ALL = 'all';

    #[\Override]
    protected function _construct()
    {
        $this->_init('api2/acl_global_rule');
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

    public function getResourceId(): ?string
    {
        $value = $this->getData('resource_id');
        return $value === null ? null : (string) $value;
    }

    public function setResourceId(?string $value): static
    {
        return $this->setData('resource_id', $value);
    }

    public function getPrivilege(): ?string
    {
        $value = $this->getData('privilege');
        return $value === null ? null : (string) $value;
    }

    public function setPrivilege(?string $value): static
    {
        return $this->setData('privilege', $value);
    }
}
