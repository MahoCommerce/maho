<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Checkout_Model_Config_GuestCheckout extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getValue() === '0') {
            $customerAccountsEnabled = $this->_getCustomerAccountsEnabledValue();
            if (!$customerAccountsEnabled) {
                Mage::throwException(
                    Mage::helper('checkout')->__('Guest checkout cannot be disabled because customer accounts are disabled in the frontend. Please enable customer accounts first.'),
                );
            }
        }

        return parent::_beforeSave();
    }

    protected function _getCustomerAccountsEnabledValue(): bool
    {
        return Mage::getStoreConfigFlag('customer/account/enabled_in_frontend', $this->getScopeId());
    }
}
