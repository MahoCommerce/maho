<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Api_Model_Resource_Rules_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Resource collection initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('api/rules');
    }

    /**
     * Retrieve rules by role
     *
     * @param string $id
     * @return $this
     */
    public function getByRoles($id)
    {
        $this->getSelect()->where('role_id = ?', (int) $id);
        return $this;
    }

    /**
     * Add sort by length
     *
     * @return $this
     */
    public function addSortByLength()
    {
        $this->getSelect()->columns(['length' => $this->getConnection()->getLengthSql('resource_id')])
            ->order('length DESC');
        return $this;
    }
}
