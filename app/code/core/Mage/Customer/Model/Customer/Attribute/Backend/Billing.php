<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Model_Customer_Attribute_Backend_Billing extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * @param Mage_Customer_Model_Customer $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $defaultBilling = $object->getDefaultBilling();
        if (is_null($defaultBilling)) {
            $object->unsetDefaultBilling();
        }
        return $this;
    }

    /**
     * @param Mage_Customer_Model_Customer $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        if ($defaultBilling = $object->getDefaultBilling()) {
            $addressId = false;
            /**
             * post_index set in customer save action for address
             * this is $_POST array index for address
             */
            foreach ($object->getAddresses() as $address) {
                if ($address->getPostIndex() == $defaultBilling) {
                    $addressId = $address->getId();
                }
            }
            if ($addressId) {
                $object->setDefaultBilling($addressId);
                $this->getAttribute()->getEntity()
                    ->saveAttribute($object, $this->getAttribute()->getAttributeCode());
            }
        }
        return $this;
    }
}
