<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Order billing address backend
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Order_Attribute_Backend_Billing extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Perform operation before save
     *
     * @param Varien_Object $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $billingAddressId = $object->getBillingAddressId();
        if ($billingAddressId === null) {
            $object->unsetBillingAddressId();
        }
        return $this;
    }

    /**
     * Perform operation after save
     *
     * @param Varien_Object $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        $billingAddressId = false;
        foreach ($object->getAddressesCollection() as $address) {
            if ($address->getAddressType() == 'billing') {
                $billingAddressId = $address->getId();
            }
        }
        if ($billingAddressId) {
            $object->setBillingAddressId($billingAddressId);
            $this->getAttribute()->getEntity()->saveAttribute($object, $this->getAttribute()->getAttributeCode());
        }
        return $this;
    }
}
