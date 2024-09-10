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
 * Product form boolean field helper
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Boolean extends Varien_Data_Form_Element_Select
{
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setValues([
            [
                'label' => Mage::helper('catalog')->__('No'),
                'value' => 0,
            ],
            [
                'label' => Mage::helper('catalog')->__('Yes'),
                'value' => 1,
            ],
        ]);
    }
}
