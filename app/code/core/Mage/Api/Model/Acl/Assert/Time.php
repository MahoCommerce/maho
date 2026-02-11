<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api_Model_Acl_Assert_Time implements Mage_Api_Model_Acl_Assert_Interface
{
    /**
     * Assert time
     *
     * @param string|null $privilege
     * @return bool|null
     */
    #[\Override]
    public function assert(
        Mage_Api_Model_Acl $acl,
        ?Mage_Api_Model_Acl_Role $role = null,
        ?Mage_Api_Model_Acl_Resource $resource = null,
        $privilege = null,
    ) {
        return $this->_isCleanTime(time());
    }

    /**
     * @param int $time
     * @return bool|null
     */
    protected function _isCleanTime($time)
    {
        // ...
    }
}
