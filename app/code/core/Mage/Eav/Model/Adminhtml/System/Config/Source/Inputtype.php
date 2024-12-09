<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product attribute source input types
 *
 * @deprecated Instead use Mage::helper('eav')->getInputTypes()
 * @see Mage_Eav_Helper_Data::getInputTypes()
 */
class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return Mage::helper('eav')->getInputTypes('default');
    }
}
