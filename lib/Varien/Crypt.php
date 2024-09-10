<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Crypt
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Crypt factory
 *
 * @category   Varien
 * @package    Varien_Crypt
 */
class Varien_Crypt
{
    /**
     * Factory method to return requested cipher logic
     *
     * @param string $method
     * @return Varien_Crypt_Abstract
     */
    public static function factory($method = 'mcrypt')
    {
        $uc = str_replace(' ', '_', ucwords(str_replace('_', ' ', $method)));
        $className = 'Varien_Crypt_' . $uc;
        return new $className();
    }
}
