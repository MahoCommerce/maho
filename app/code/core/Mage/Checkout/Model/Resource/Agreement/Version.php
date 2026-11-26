<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

class Mage_Checkout_Model_Resource_Agreement_Version extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('checkout/agreement_version', 'version_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if ($object->getId()) {
            Mage::throwException(Mage::helper('checkout')->__('This record cannot change after its save.'));
        }
        if (!$object->getData('created_at')) {
            $object->setData('created_at', Mage::app()->getLocale()->formatDateForDb('now'));
        }
        return parent::_beforeSave($object);
    }
}
