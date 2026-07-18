<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Model_System_Config_Backend_Active extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getValue() === '1') {
            if (!$this->_hasCredentialsInRequest()) {
                $config = Mage::getModel('paypal/config');

                if (!$config->hasCredentials()) {
                    Mage::throwException(
                        Mage::helper('paypal')->__('PayPal Client ID and Client Secret are required when a PayPal payment method is enabled.'),
                    );
                }
            }
        }

        return parent::_beforeSave();
    }

    protected function _hasCredentialsInRequest(): bool
    {
        $groups = Mage::app()->getRequest()->getPost('groups');
        if (!is_array($groups)) {
            return false;
        }
        $clientId = $groups['paypal_credentials']['fields']['client_id']['value'] ?? '';
        $clientSecret = $groups['paypal_credentials']['fields']['client_secret']['value'] ?? '';
        $isPlaceholder = static fn(string $v): bool => $v === '' || (bool) preg_match('/^\*+$/', $v);
        return !$isPlaceholder($clientId) && !$isPlaceholder($clientSecret);
    }
}
