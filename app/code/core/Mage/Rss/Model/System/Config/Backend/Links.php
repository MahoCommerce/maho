<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Rss
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Cache cleaner backend model
 *
 * @category   Mage
 * @package    Mage_Rss
 */
class Mage_Rss_Model_System_Config_Backend_Links extends Mage_Core_Model_Config_Data
{
    /**
     * Invalidate cache type, when value was changed
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->isValueChanged()) {
            Mage::app()->getCacheInstance()->invalidateType(Mage_Core_Block_Abstract::CACHE_GROUP);
        }
        return $this;
    }
}
