<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Auth_User_Admin extends Mage_Api2_Model_Auth_User_Abstract
{
    /**
     * User type
     */
    public const USER_TYPE = 'admin';

    /**
     * Retrieve user human-readable label
     *
     * @return string
     */
    #[\Override]
    public function getLabel()
    {
        return Mage::helper('api2')->__('Admin');
    }

    /**
     * Retrieve user role
     *
     * @return int
     * @throws Exception
     */
    #[\Override]
    public function getRole()
    {
        if (!$this->_role) {
            if (!$this->getUserId()) {
                throw new Exception('Admin identifier is not set');
            }

            /** @var Mage_Api2_Model_Resource_Acl_Global_Role_Collection $collection */
            $collection = Mage::getModel('api2/acl_global_role')->getCollection();
            $collection->addFilterByAdminId($this->getUserId());

            /** @var Mage_Api2_Model_Acl_Global_Role $role */
            $role = $collection->getFirstItem();
            if (!$role->getId()) {
                throw new Exception('Admin role for user ID ' . $this->getUserId() . ' not found');
            }

            $this->setRole($role->getId());
        }

        return $this->_role;
    }

    /**
     * Retrieve user type
     *
     * @return string
     */
    #[\Override]
    public function getType()
    {
        return self::USER_TYPE;
    }

    /**
     * Set user role
     *
     * @param int $role
     * @return $this
     * @throws Exception
     */
    public function setRole($role)
    {
        if ($this->_role) {
            throw new Exception('Admin role has been already set to ' . $this->_role . ' for user ID ' . $this->getUserId());
        }
        $this->_role = $role;

        return $this;
    }
}
