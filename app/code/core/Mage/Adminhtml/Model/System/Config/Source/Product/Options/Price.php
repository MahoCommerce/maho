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
 * Price types mode source
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Source_Product_Options_Price
{
    public function toOptionArray()
    {
        return [
            ['value' => 'fixed', 'label' => Mage::helper('adminhtml')->__('Fixed')],
            ['value' => 'percent', 'label' => Mage::helper('adminhtml')->__('Percent')]
        ];
    }
}
