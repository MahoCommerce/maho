<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Maho info API
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Model_Maho_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve information about the current Maho installation
     *
     * @return array
     */
    public function info()
    {
        $result = [];
        $result['maho_version'] = Mage::getMahoVersion();

        return $result;
    }
}
