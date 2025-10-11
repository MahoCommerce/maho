<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Resource_Acl_Filter_Attribute extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Attribute Filter resource ID "all"
     */
    public const FILTER_RESOURCE_ALL = 'all';

    #[\Override]
    protected function _construct()
    {
        $this->_init('api2/acl_attribute', 'entity_id');
    }

    /**
     * @param string $userType
     * @param string $resourceId
     * @param Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_* $operation
     * @return string|bool|null
     */
    public function getAllowedAttributes($userType, $resourceId, $operation)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), 'allowed_attributes')
            ->where('user_type = ?', $userType)
            ->where('resource_id = ?', $resourceId)
            ->where('operation = ?', $operation);

        return $this->getReadConnection()->fetchOne($select);
    }

    /**
     * Check if ALL attributes allowed
     *
     * @param string $userType
     * @return bool
     */
    public function isAllAttributesAllowed($userType)
    {
        $resourceId = self::FILTER_RESOURCE_ALL;

        $select = $this->getReadConnection()->select()
            ->from($this->getMainTable(), new Varien_Db_Expr('COUNT(*)'))
            ->where('user_type = ?', $userType)
            ->where('resource_id = ?', $resourceId);

        return ($this->getReadConnection()->fetchOne($select) == 1);
    }
}
