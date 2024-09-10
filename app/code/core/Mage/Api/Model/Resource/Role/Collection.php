<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Api Role Resource Collection
 *
 * @category   Mage
 * @package    Mage_Api
 */
class Mage_Api_Model_Resource_Role_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Resource collection initialization
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/role');
    }

    /**
     * Aet user filter
     *
     * @param int|string $userId
     * @return $this
     */
    public function setUserFilter($userId)
    {
        $this->addFieldToFilter('user_id', $userId);
        $this->addFieldToFilter('role_type', Mage_Api_Model_Acl::ROLE_TYPE_GROUP);
        return $this;
    }

    /**
     * Set roles filter
     *
     * @return $this
     */
    public function setRolesFilter()
    {
        $this->addFieldToFilter('role_type', Mage_Api_Model_Acl::ROLE_TYPE_GROUP);
        return $this;
    }
}
