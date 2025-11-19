<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Acl_Global
{
    /**
     * Check if the operation is allowed on resources of given type type for given user type/role
     *
     * @param string $resourceType
     * @param string $operation
     * @return bool
     * @throws Mage_Api2_Exception
     */
    public function isAllowed(Mage_Api2_Model_Auth_User_Abstract $apiUser, $resourceType, $operation)
    {
        // skip user without role, e.g. Customer
        if ($apiUser->getRole() === null) {
            return true;
        }
        /** @var Mage_Api2_Model_Acl $aclInstance */
        $aclInstance = Mage::getSingleton(
            'api2/acl',
            ['resource_type' => $resourceType, 'operation' => $operation],
        );

        if (!$aclInstance->hasRole((string) $apiUser->getRole())) {
            throw new Mage_Api2_Exception('Role not found', Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
        }
        if (!$aclInstance->hasResource($resourceType)) {
            throw new Mage_Api2_Exception('Resource not found', Mage_Api2_Model_Server::HTTP_NOT_FOUND);
        }
        return $aclInstance->isAllowed((string) $apiUser->getRole(), $resourceType, $operation);
    }
}
