<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_System_Config_Backend_Active extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getValue() === '1') {
            if (!$this->_hasCredentialsInRequest()) {
                $config = Mage::getModel('maho_paypal/config');

                if (!$config->hasCredentials()) {
                    Mage::throwException(
                        Mage::helper('maho_paypal')->__('PayPal Client ID and Client Secret are required when a PayPal payment method is enabled.'),
                    );
                }
            }

            if ($this->getPath() === 'payment/paypal_vault/active') {
                $config ??= Mage::getModel('maho_paypal/config');
                $standardActive = $config->isNewMethodActive(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT)
                    || $this->_isMethodActiveInRequest('paypal_standard_checkout');
                $advancedActive = $config->isNewMethodActive(Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT)
                    || $this->_isMethodActiveInRequest('paypal_advanced_checkout');
                if (!$standardActive && !$advancedActive) {
                    Mage::throwException(
                        Mage::helper('maho_paypal')->__('Vault requires at least Standard Checkout or Advanced Checkout to be enabled.'),
                    );
                }
            }
        }

        return parent::_beforeSave();
    }

    protected function _isMethodActiveInRequest(string $groupId): bool
    {
        $groups = Mage::app()->getRequest()->getPost('groups');
        if (!is_array($groups)) {
            return false;
        }
        return ($groups[$groupId]['fields']['active']['value'] ?? '0') === '1';
    }

    protected function _hasCredentialsInRequest(): bool
    {
        $groups = Mage::app()->getRequest()->getPost('groups');
        if (!is_array($groups)) {
            return false;
        }
        $clientId = $groups['maho_paypal_credentials']['fields']['client_id']['value'] ?? '';
        $clientSecret = $groups['maho_paypal_credentials']['fields']['client_secret']['value'] ?? '';
        $isPlaceholder = static fn(string $v): bool => $v === '' || (bool) preg_match('/^\*+$/', $v);
        return !$isPlaceholder($clientId) && !$isPlaceholder($clientSecret);
    }
}
