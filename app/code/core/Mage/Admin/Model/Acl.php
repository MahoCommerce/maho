<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @property Mage_Admin_Model_Acl_Role_Registry $_roleRegistry
 *
 * @method Mage_Admin_Model_Resource_Acl _getResource()
 * @method Mage_Admin_Model_Resource_Acl getResource()
 */
class Mage_Admin_Model_Acl extends Zend_Acl
{
    /**
     * All the group roles are prepended by G
     *
     */
    public const ROLE_TYPE_GROUP = 'G';

    /**
     * All the user roles are prepended by U
     *
     */
    public const ROLE_TYPE_USER = 'U';

    /**
     * Permission level to deny access
     *
     */
    public const RULE_PERM_DENY = 0;

    /**
     * Permission level to inheric access from parent role
     *
     */
    public const RULE_PERM_INHERIT = 1;

    /**
     * Permission level to allow access
     *
     */
    public const RULE_PERM_ALLOW = 2;

    /**
     * Get role registry object or create one
     *
     * @return Mage_Admin_Model_Acl_Role_Registry
     */
    #[\Override]
    protected function _getRoleRegistry()
    {
        if ($this->_roleRegistry === null) {
            $this->_roleRegistry = Mage::getModel('admin/acl_role_registry');
        }
        return $this->_roleRegistry;
    }

    /**
     * Add parent to role object
     *
     * @param Zend_Acl_Role|string $role
     * @param Zend_Acl_Role|string $parent
     * @return $this
     */
    public function addRoleParent($role, $parent)
    {
        $this->_getRoleRegistry()->addParent($role, $parent);
        return $this;
    }
}
