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
 * API2 filter ACL attribute model
 *
 * @package    Mage_Api2
 *
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute_Collection getCollection()
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute_Collection getResourceCollection()
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute getResource()
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute _getResource()
 *
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api2_Model_Acl_Filter_Attribute extends Mage_Core_Model_Abstract
{
    /**
     * Permissions model
     *
     * @var Mage_Api2_Model_Acl_Filter_Attribute_ResourcePermission
     */
    protected $_permissionModel;

    #[\Override]
    protected function _construct()
    {
        $this->_init('api2/acl_filter_attribute');
    }

    /**
     * Get pairs resources-permissions for current attribute
     *
     * @return Mage_Api2_Model_Acl_Filter_Attribute_ResourcePermission
     */
    public function getPermissionModel()
    {
        if ($this->_permissionModel == null) {
            $this->_permissionModel = Mage::getModel('api2/acl_filter_attribute_resourcePermission');
        }
        return $this->_permissionModel;
    }

    public function getUserType(): ?string
    {
        $value = $this->getData('user_type');
        return $value === null ? null : (string) $value;
    }

    public function setUserType(?string $value): static
    {
        return $this->setData('user_type', $value);
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

    public function getOperation(): ?string
    {
        $value = $this->getData('operation');
        return $value === null ? null : (string) $value;
    }

    public function setOperation(?string $value): static
    {
        return $this->setData('operation', $value);
    }

    public function getAllowedAttributes(): ?string
    {
        $value = $this->getData('allowed_attributes');
        return $value === null ? null : (string) $value;
    }

    public function setAllowedAttributes(?string $value): static
    {
        return $this->setData('allowed_attributes', $value);
    }
}
