<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product attribute source model for enable/disable option
 *
 * @category   Mage
 * @package    Mage_Catalog
 */
class Mage_Catalog_Model_Product_Attribute_Source_Boolean extends Mage_Eav_Model_Entity_Attribute_Source_Boolean
{
    /**
     * Retrieve all attribute options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                [
                    'label' => Mage::helper('catalog')->__('Yes'),
                    'value' => 1
                ],
                [
                    'label' => Mage::helper('catalog')->__('No'),
                    'value' => 0
                ],
                [
                    'label' => Mage::helper('catalog')->__('Use config'),
                    'value' => 2
                ]
            ];
        }
        return $this->_options;
    }
}
