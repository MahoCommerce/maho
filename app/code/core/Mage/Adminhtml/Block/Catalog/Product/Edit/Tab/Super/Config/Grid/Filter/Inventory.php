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
 * Configurable product associated products in stock filter
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Super_Config_Grid_Filter_Inventory extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    /**
     * @return array
     */
    #[\Override]
    protected function _getOptions()
    {
        return [
            [
                'value' =>  '',
                'label' =>  ''
            ],
            [
                'value' =>  1,
                'label' =>  Mage::helper('catalog')->__('In Stock')
            ],
            [
                'value' =>  0,
                'label' =>  Mage::helper('catalog')->__('Out of Stock')
            ]
        ];
    }
}
