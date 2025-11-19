<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Admin_Helper_Rules_Fallback extends Mage_Core_Helper_Abstract
{
    /**
     * Fallback to resource parent node
     */
    protected function _getParentResourceId(string $resourceId): string
    {
        $resourcePathInfo = explode('/', $resourceId);
        array_pop($resourcePathInfo);
        return implode('/', $resourcePathInfo);
    }

    /**
     * Recursively resolves ACL permissions for a resource by traversing up the hierarchy tree
     *
     * This method implements permission inheritance for ACL resources. When a specific resource
     * doesn't have an explicit permission defined, it walks up the resource path hierarchy to
     * find inherited permissions from parent resources.
     *
     * For example, if "admin/system/config/advanced" has no permission set, it will check:
     * 1. admin/system/config/advanced (not found)
     * 2. admin/system/config (checks here)
     * 3. admin/system (checks here if previous not found)
     * 4. admin (checks here if previous not found)
     * 5. Returns default value if nothing found
     *
     * @param array &$resources Array of resource IDs mapped to their permission values (passed by reference)
     * @param string $resourceId Resource identifier using slash-separated hierarchy (e.g., "admin/system/config")
     * @param string $defaultValue Permission to return if no explicit or inherited permission is found
     *                             Defaults to RULE_PERMISSION_DENIED
     *
     * @return string The resolved permission value for the resource (either explicit, inherited, or default)
     */
    public function fallbackResourcePermissions(
        array &$resources,
        string $resourceId,
        string $defaultValue = Mage_Admin_Model_Rules::RULE_PERMISSION_DENIED,
    ): string {
        if (empty($resourceId)) {
            return $defaultValue;
        }

        if (!array_key_exists($resourceId, $resources)) {
            return $this->fallbackResourcePermissions($resources, $this->_getParentResourceId($resourceId));
        }

        return $resources[$resourceId];
    }
}
