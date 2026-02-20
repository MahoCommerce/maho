<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Api_Model_Acl extends \Laminas\Permissions\Acl\Acl
{
    protected ?Mage_Api_Model_Acl_Role_Registry $_roleRegistry = null;

    /**
     * All the group roles are prepended by G
     */
    public const ROLE_TYPE_GROUP = 'G';

    /**
     * All the user roles are prepended by U
     */
    public const ROLE_TYPE_USER = 'U';

    /**
     * User types for store access
     * G - Guest customer (anonymous)
     * C - Authenticated customer
     * A - Authenticated admin user
     */
    public const USER_TYPE_GUEST    = 'G';
    public const USER_TYPE_CUSTOMER = 'C';
    public const USER_TYPE_ADMIN    = 'A';

    /**
     * Permission level to deny access
     */
    public const RULE_PERM_DENY = 0;

    /**
     * Permission level to inheric access from parent role
     */
    public const RULE_PERM_INHERIT = 1;

    /**
     * Permission level to allow access
     */
    public const RULE_PERM_ALLOW = 2;

    /**
     * Get role registry object or create one
     */
    #[\Override]
    protected function getRoleRegistry(): Mage_Api_Model_Acl_Role_Registry
    {
        if ($this->_roleRegistry === null) {
            $this->_roleRegistry = Mage::getModel('api/acl_role_registry');
        }
        return $this->_roleRegistry;
    }

    /**
     * Add parent to role object
     */
    public function addRoleParent(
        \Laminas\Permissions\Acl\Role\RoleInterface|string $role,
        array|\Laminas\Permissions\Acl\Role\RoleInterface|string $parent,
    ): self {
        $this->getRoleRegistry()->addParent($role, $parent);
        return $this;
    }
}
