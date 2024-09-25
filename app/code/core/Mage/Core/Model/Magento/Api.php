<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Maho info API
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Model_Magento_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve information about the current Maho installation
     *
     * @return array
     */
    public function info()
    {
        $result = [];
        $result['magento_edition'] = Mage::getEdition();
        $result['magento_version'] = Mage::getVersion();
        $result['maho_version'] = Mage::getMahoVersion();

        return $result;
    }
}
