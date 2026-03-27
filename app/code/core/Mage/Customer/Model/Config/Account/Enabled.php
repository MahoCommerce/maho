<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Customer_Model_Config_Account_Enabled extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getValue() === '0') {
            $guestCheckoutEnabled = $this->_getGuestCheckoutValue();
            if (!$guestCheckoutEnabled) {
                Mage::throwException(
                    Mage::helper('customer')->__('Customer accounts cannot be disabled because guest checkout is not allowed. Please enable guest checkout first.'),
                );
            }
        }

        return parent::_beforeSave();
    }

    protected function _getGuestCheckoutValue(): bool
    {
        return Mage::getStoreConfigFlag('checkout/options/guest_checkout');
    }
}
