<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Model_Customer_Attribute_Source_Website extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    /**
     * Retrieve Full Option values array
     *
     * @param bool $withEmpty       Argument has no effect, included for PHP 7.2 method signature compatibility
     * @param bool $defaultValues   Argument has no effect, included for PHP 7.2 method signature compatibility
     * @return array
     */
    #[\Override]
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        if (!$this->_options) {
            $this->_options = Mage::getSingleton('adminhtml/system_store')->getWebsiteValuesForForm(true, true);
        }

        return $this->_options;
    }

    #[\Override]
    public function getOptionText($value)
    {
        if (!$this->_options) {
            $this->_options = $this->getAllOptions();
        }
        foreach ($this->_options as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }
}
