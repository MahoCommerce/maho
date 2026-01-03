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

class Mage_Customer_Model_Customer_Attribute_Backend_Password extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Special processing before attribute save:
     * a) check some rules for password
     * b) transform temporary attribute 'password' into real attribute 'password_hash'
     *
     * @param Mage_Customer_Model_Customer $object
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function beforeSave($object)
    {
        $password = trim($object->getPassword());
        $len = Mage::helper('core/string')->strlen($password);
        if ($len) {
            $minPasswordLength = Mage::getModel('customer/customer')->getMinPasswordLength();
            if ($len < $minPasswordLength) {
                Mage::throwException(Mage::helper('customer')->__(
                    'The password must have at least %d characters. Leading or trailing spaces will be ignored.',
                    $minPasswordLength,
                ));
            }
            $object->setPasswordHash($object->hashPassword($password));
        }
        return $this;
    }

    /**
     * Validate object
     *
     * @param Mage_Customer_Model_Customer $object
     * @throws Mage_Eav_Exception
     * @return bool
     */
    #[\Override]
    public function validate($object)
    {
        if (!$password = $object->getPassword()) {
            return parent::validate($object);
        }

        if ($password == $object->getPasswordConfirm()) {
            return true;
        }

        return parent::validate($object);
    }
}
