<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Invoice backend model for order attribute
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Order_Invoice_Attribute_Backend_Order extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Method is invoked before save
     *
     * @inheritDoc
     */
    #[\Override]
    public function beforeSave($object)
    {
        if ($object->getOrder()) {
            $object->setOrderId($object->getOrder()->getId());
            $object->setBillingAddressId($object->getOrder()->getBillingAddress()->getId());
        }
        return parent::beforeSave($object);
    }
}
