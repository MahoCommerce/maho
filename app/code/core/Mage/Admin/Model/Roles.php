<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

/**
 * @method Mage_Admin_Model_Resource_Roles _getResource()
 * @method Mage_Admin_Model_Resource_Roles getResource()
 * @method Mage_Admin_Model_Resource_Roles_Collection getResourceCollection()
 */
class Mage_Admin_Model_Roles extends Mage_Core_Model_Abstract
{
    /**
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'admin_roles';

    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/roles');
    }

    /**
     * Update object into database
     *
     * @return $this
     */
    public function update()
    {
        $this->getResource()->update($this);
        return $this;
    }

    /**
     * Retrieve users collection
     *
     * @return Mage_Admin_Model_Resource_Roles_User_Collection
     */
    public function getUsersCollection()
    {
        return Mage::getResourceModel('admin/roles_user_collection');
    }

    /**
     * Return tree of acl resources
     *
     * @return \Maho\Simplexml\Element|array
     */
    public function getResourcesTree()
    {
        return $this->_buildResourcesArray(null, null, null, null, true);
    }

    /**
     * Return list of acl resources
     *
     * @return \Maho\Simplexml\Element|array
     */
    public function getResourcesList()
    {
        return $this->_buildResourcesArray();
    }

    /**
     * Return list of acl resources in 2D format
     *
     * @return \Maho\Simplexml\Element|array
     */
    public function getResourcesList2D()
    {
        return $this->_buildResourcesArray(null, null, null, true);
    }

    /**
     * Return users for role
     *
     * @return array|false
     */
    public function getRoleUsers()
    {
        return $this->getResource()->getRoleUsers($this);
    }

    /**
     * Build resources array process
     *
     * @param  null|string $parentName
     * @param  null|int $level
     * @param  null|mixed $represent2Darray
     * @param  bool $rawNodes
     * @param  string $module
     * @return \Maho\Simplexml\Element|false|array
     */
    protected function _buildResourcesArray(
        ?\Maho\Simplexml\Element $resource = null,
        $parentName = null,
        $level = 0,
        $represent2Darray = null,
        $rawNodes = false,
        $module = 'adminhtml',
    ) {
        static $result;
        if (is_null($resource)) {
            $resource = Mage::getSingleton('admin/config')->getAdminhtmlConfig()->getNode('acl/resources');
            $resourceName = null;
            $level = -1;
        } else {
            $resourceName = $parentName;
            if (!empty($resource->children()) && $resource->getName() !== 'children') {
                $resourceName = (is_null($parentName) ? '' : $parentName . '/') . $resource->getName();

                //assigning module for its' children nodes
                if ($resource->getAttribute('module')) {
                    $module = (string) $resource->getAttribute('module');
                }

                if ($rawNodes) {
                    $resource->addAttribute('aclpath', $resourceName);
                    $resource->addAttribute('module_c', $module);
                }

                if (is_null($represent2Darray)) {
                    $result[$resourceName]['name']  = Mage::helper($module)->__((string) $resource->title);
                    $result[$resourceName]['level'] = $level;
                } else {
                    $result[] = $resourceName;
                }
            }
        }

        //check children and run recursion if they exists
        $children = $resource->children();
        foreach ($children as $key => $child) {
            if ((string) $child->disabled === '1') {
                $resource->{$key} = null;
                continue;
            }
            $this->_buildResourcesArray($child, $resourceName, $level + 1, $represent2Darray, $rawNodes, $module);
        }

        if ($rawNodes) {
            return $resource;
        }
        return $result;
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function getParentId(): ?int
    {
        $value = $this->getData('parent_id');
        return $value === null ? null : (int) $value;
    }

    public function setParentId(?int $value): static
    {
        return $this->setData('parent_id', $value);
    }

    public function getPid(): ?int
    {
        $value = $this->getData('pid');
        return $value === null ? null : (int) $value;
    }

    public function getRoleName(): ?string
    {
        $value = $this->getData('role_name');
        return $value === null ? null : (string) $value;
    }

    public function setRoleName(?string $value): static
    {
        return $this->setData('role_name', $value);
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

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

    public function getTreeLevel(): ?int
    {
        $value = $this->getData('tree_level');
        return $value === null ? null : (int) $value;
    }

    public function setTreeLevel(?int $value): static
    {
        return $this->setData('tree_level', $value);
    }

    public function getUserId(): ?int
    {
        $value = $this->getData('user_id');
        return $value === null ? null : (int) $value;
    }

    public function setUserId(?int $value): static
    {
        return $this->setData('user_id', $value);
    }

}
