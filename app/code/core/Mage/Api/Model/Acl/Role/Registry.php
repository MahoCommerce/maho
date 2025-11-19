<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Api_Model_Acl_Role_Registry extends \Laminas\Permissions\Acl\Role\Registry
{
    /**
     * Add parent to the $role node
     */
    public function addParent(
        \Laminas\Permissions\Acl\Role\RoleInterface|string $role,
        array|\Laminas\Permissions\Acl\Role\RoleInterface|string $parents,
    ): self {
        try {
            if ($role instanceof \Laminas\Permissions\Acl\Role\RoleInterface) {
                $roleId = $role->getRoleId();
            } else {
                $roleId = $role;
                $role = $this->get($role);
            }
        } catch (\Laminas\Permissions\Acl\Exception\InvalidArgumentException $e) {
            throw new \Laminas\Permissions\Acl\Exception\InvalidArgumentException("Child Role id '$roleId' does not exist");
        }

        if (!is_array($parents)) {
            $parents = [$parents];
        }
        foreach ($parents as $parent) {
            try {
                if ($parent instanceof \Laminas\Permissions\Acl\Role\RoleInterface) {
                    $roleParentId = $parent->getRoleId();
                } else {
                    $roleParentId = $parent;
                }
                $roleParent = $this->get($roleParentId);
            } catch (\Laminas\Permissions\Acl\Exception\InvalidArgumentException $e) {
                throw new \Laminas\Permissions\Acl\Exception\InvalidArgumentException("Parent Role id '$roleParentId' does not exist");
            }
            $this->roles[$roleId]['parents'][$roleParentId] = $roleParent;
            $this->roles[$roleParentId]['children'][$roleId] = $role;
        }
        return $this;
    }
}
