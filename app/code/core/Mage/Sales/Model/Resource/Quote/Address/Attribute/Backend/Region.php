<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Quote address attribute backend region resource modelarray
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Quote_Address_Attribute_Backend_Region extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Set region to the attribute
     *
     * @param Varien_Object|Mage_Sales_Model_Quote_Address $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        if (is_numeric($object->getRegion())) {
            $region = Mage::getModel('directory/region')->load((int)$object->getRegion());
            if ($region) {
                $object->setRegionId($region->getId());
                $object->setRegion($region->getCode());
            }
        }

        return $this;
    }
}
