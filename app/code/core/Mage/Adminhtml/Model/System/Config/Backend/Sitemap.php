<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Backend_Sitemap extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if ($value < 0 || $value > 1) {
            throw new Exception(Mage::helper('sitemap')->__('The priority must be between 0 and 1.'));
        }
        if (($value == 0) && !($value === '0' || $value === '0.0')) {
            throw new Exception(Mage::helper('sitemap')->__('The priority must be between 0 and 1.'));
        }
        return $this;
    }
}
