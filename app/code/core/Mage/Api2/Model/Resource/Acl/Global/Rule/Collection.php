<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Api2 global ACL rule resource collection model
 *
 * @category   Mage
 * @package    Mage_Api2
 */
class Mage_Api2_Model_Resource_Acl_Global_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialize collection model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('api2/acl_global_rule');
    }

    /**
     * Add filtering by role ID
     *
     * @param int $roleId
     * @return $this
     */
    public function addFilterByRoleId($roleId)
    {
        $this->addFilter('role_id', $roleId, 'public');
        return $this;
    }
}
