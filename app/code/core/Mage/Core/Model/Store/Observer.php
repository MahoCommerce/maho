<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Store observer
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Model_Store_Observer
{
    public function cleanCache()
    {
        Mage::app()->cleanCache([Mage_Core_Model_Store::CACHE_TAG]);
    }
}
