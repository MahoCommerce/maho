<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Tax_Model_System_Config_Source_Tax_Display_Type
{
    protected $_options;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->_options) {
            $this->_options = [];
            $this->_options[] = ['value' => Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX, 'label' => Mage::helper('tax')->__('Excluding Tax')];
            $this->_options[] = ['value' => Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX, 'label' => Mage::helper('tax')->__('Including Tax')];
            $this->_options[] = ['value' => Mage_Tax_Model_Config::DISPLAY_TYPE_BOTH, 'label' => Mage::helper('tax')->__('Including and Excluding Tax')];
        }
        return $this->_options;
    }
}
