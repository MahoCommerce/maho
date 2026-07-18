<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Model_Acl_Assert_Ip implements Mage_Admin_Model_Acl_Assert_Interface
{
    /**
     * Check whether ip is allowed
     *
     * @param string|null $privilege
     * @return bool|null
     */
    #[\Override]
    public function assert(
        Mage_Admin_Model_Acl $acl,
        ?Mage_Admin_Model_Acl_Role $role = null,
        ?Mage_Admin_Model_Acl_Resource $resource = null,
        $privilege = null,
    ) {
        return $this->_isCleanIP(Mage::helper('core/http')->getRemoteAddr());
    }

    /**
     * @param string|false $ip
     * @return bool|null
     */
    protected function _isCleanIP($ip)
    {
        // ...
    }
}
