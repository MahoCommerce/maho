<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Entity_Order_Invoice_Attribute_Backend_Item extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * @param Varien_Object $object
     * @return Mage_Eav_Model_Entity_Attribute_Backend_Abstract
     */
    #[\Override]
    public function afterSave($object)
    {
        if ($object->getOrderItem()) {
            $object->getOrderItem()->save();
        }
        return parent::beforeSave($object);
    }
}
