<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Admin_Model_Resource_Roles_User_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/user');
    }

    /**
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
