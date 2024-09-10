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
 * Roles User Resource Collection
 *
 * @category   Mage
 * @package    Mage_Api
 */
class Mage_Api_Model_Resource_Roles_User_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Resource collection initialization
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/user');
    }

    /**
     * Init collection select
     *
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        parent::_initSelect();

        $this->getSelect()->where('user_id > 0');

        return $this;
    }
}
