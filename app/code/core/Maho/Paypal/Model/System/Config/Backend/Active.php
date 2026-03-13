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
            $config = Mage::getModel('maho_paypal/config');
            assert($config instanceof Maho_Paypal_Model_Config);

            if (!$config->hasCredentials()) {
                Mage::throwException(
                    Mage::helper('maho_paypal')->__('PayPal Client ID and Client Secret are required when a PayPal payment method is enabled.'),
                );
            }

            if ($this->getPath() === 'payment/paypal_vault/active') {
                $standardActive = $config->isNewMethodActive(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);
                $advancedActive = $config->isNewMethodActive(Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT);
                if (!$standardActive && !$advancedActive) {
                    Mage::throwException(
                        Mage::helper('maho_paypal')->__('Vault requires at least Standard Checkout or Advanced Checkout to be enabled.'),
                    );
                }
            }
        }

        return parent::_beforeSave();
    }
}
