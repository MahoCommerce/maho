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
 * @method int getRoleId()
 * @method $this setRoleId(int $roleId)
 * @method string getResourceId()
 * @method $this setResourceId(string $resource)
 * @method int getPermission()
 * @method $this setPermission(int $permission)
 * @method string getPrivilege()
 * @method $this setPrivilege(string $privilege)
 * @method string getAllowedAttributes()
 * @method $this setAllowedAttributes(string $allowedAttributes)
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
}
