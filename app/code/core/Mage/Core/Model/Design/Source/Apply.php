<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @deprecated after 1.4.1.0.
 */
class Mage_Core_Model_Design_Source_Apply extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $optionArray = [
                1 => Mage::helper('core')->__('This category and all its child elements'),
                3 => Mage::helper('core')->__('This category and its products only'),
                4 => Mage::helper('core')->__('This category and its child categories only'),
                2 => Mage::helper('core')->__('This category only'),
            ];

            foreach ($optionArray as $k => $label) {
                $this->_options[] = ['value' => $k, 'label' => $label];
            }
        }

        return $this->_options;
    }
}
