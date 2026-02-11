<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * API2 filter ACL attribute model
 *
 * @package    Mage_Api2
 *
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute_Collection getCollection()
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute_Collection getResourceCollection()
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute getResource()
 * @method Mage_Api2_Model_Resource_Acl_Filter_Attribute _getResource()
 * @method string getUserType()
 * @method $this setUserType() setUserType(string $type)
 * @method string getResourceId()
 * @method $this setResourceId() setResourceId(string $resource)
 * @method string getOperation()
 * @method $this setOperation() setOperation(string $operation)
 * @method string getAllowedAttributes()
 * @method $this setAllowedAttributes() setAllowedAttributes(string $attributes)
 */

class Mage_Api2_Model_Acl_Filter_Attribute extends Mage_Core_Model_Abstract
{
    /**
     * Permissions model
     *
     * @var Mage_Api2_Model_Acl_Filter_Attribute_ResourcePermission
     */
    protected $_permissionModel;

    #[\Override]
    protected function _construct()
    {
        $this->_init('api2/acl_filter_attribute');
    }

    /**
     * Get pairs resources-permissions for current attribute
     *
     * @return Mage_Api2_Model_Acl_Filter_Attribute_ResourcePermission
     */
    public function getPermissionModel()
    {
        if ($this->_permissionModel == null) {
            $this->_permissionModel = Mage::getModel('api2/acl_filter_attribute_resourcePermission');
        }
        return $this->_permissionModel;
    }
}
