<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

/**
 * @method Mage_Api_Model_Resource_Roles _getResource()
 * @method Mage_Api_Model_Resource_Roles getResource()
 *
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_Model_Roles extends Mage_Core_Model_Abstract
{
    /**
     * Filters
     *
     * @var array
     */
    protected $_filters;

    #[\Override]
    protected function _construct()
    {
        $this->_init('api/roles');
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
     * @return Mage_Api_Model_Resource_Roles_User_Collection
     */
    public function getUsersCollection()
    {
        return Mage::getResourceModel('api/roles_user_collection');
    }

    /**
     * @return array|false|\Maho\Simplexml\Element
     */
    public function getResourcesTree()
    {
        return $this->_buildResourcesArray(null, null, 0, null, true);
    }

    /**
     * @return array|false|\Maho\Simplexml\Element
     */
    public function getResourcesList()
    {
        return $this->_buildResourcesArray();
    }

    /**
     * @return array|false|\Maho\Simplexml\Element
     */
    public function getResourcesList2D()
    {
        return $this->_buildResourcesArray(null, null, 0, true);
    }

    /**
     * @return array
     */
    public function getRoleUsers()
    {
        return $this->getResource()->getRoleUsers($this);
    }

    /**
     * @param string|null $parentName
     * @param int $level
     * @param bool|null $represent2Darray
     * @param bool $rawNodes
     * @param string $module
     * @return array|false|\Maho\Simplexml\Element
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
            $resource = Mage::getSingleton('api/config')->getNode('acl/resources');
            $resourceName = null;
            $level = -1;
        } else {
            $resourceName = $parentName;
            if ($resource->getName() != 'title' && $resource->getName() != 'sort_order'
                && $resource->getName() != 'children'
            ) {
                $resourceName = (is_null($parentName) ? '' : $parentName . '/') . $resource->getName();

                //assigning module for its' children nodes
                if ($resource->getAttribute('module')) {
                    $module = (string) $resource->getAttribute('module');
                }

                if ($rawNodes) {
                    $resource->addAttribute('aclpath', $resourceName);
                }

                $resource->title = Mage::helper($module)->__((string) $resource->title);

                if (is_null($represent2Darray)) {
                    $result[$resourceName]['name']  = (string) $resource->title;
                    $result[$resourceName]['level'] = $level;
                } else {
                    $result[] = $resourceName;
                }
            }
        }

        $children = $resource->children();
        if (empty($children)) {
            if ($rawNodes) {
                return $resource;
            }
            return $result;
        }
        foreach ($children as $child) {
            $this->_buildResourcesArray($child, $resourceName, $level + 1, $represent2Darray, $rawNodes, $module);
        }
        if ($rawNodes) {
            return $resource;
        }
        return $result;
    }

    /**
     * Filter data before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->filter();
        parent::_beforeSave();
        return $this;
    }

    /**
     * Filter set data
     *
     * @return $this
     */
    public function filter()
    {
        $data = $this->getData();
        if (!$this->_filters || !$data) {
            return $this;
        }
        /** @var Mage_Core_Model_Input_Filter $filter */
        $filter = Mage::getModel('core/input_filter');
        $filter->setFilters($this->_filters);
        $this->setData($filter->filter($data));
        return $this;
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
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
