<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Cache cleaner backend model
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Backend_Cache extends Mage_Core_Model_Config_Data
{
    /**
     * Cache tags to clean
     *
     * @var array
     */
    protected $_cacheTags = [];

    /**
     * Clean cache, value was changed
     *
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->isValueChanged()) {
            Mage::app()->cleanCache($this->_cacheTags);
        }
        return $this;
    }
}
