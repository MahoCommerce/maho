<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Config extends Maho\DataObject
{
    public const METHOD_STANDARD_CHECKOUT = 'paypal_standard_checkout';
    public const METHOD_ADVANCED_CHECKOUT = 'paypal_advanced_checkout';
    public const METHOD_VAULT = 'paypal_vault';

    public const PAYMENT_ACTION_AUTHORIZE = 'authorize';
    public const PAYMENT_ACTION_CAPTURE = 'capture';

    public const JS_SDK_URL_SANDBOX = 'https://www.sandbox.paypal.com/web-sdk/v6/core';
    public const JS_SDK_URL_LIVE = 'https://www.paypal.com/web-sdk/v6/core';

    /**
     * @deprecated Legacy PayPal methods - use new Maho_Paypal methods instead
     */
    public const DEPRECATED_METHODS = [
        'paypal_express',
        'paypal_direct',
        'paypal_standard',
        'paypaluk_express',
        'paypaluk_direct',
        'verisign',
        'payflow_link',
        'payflow_advanced',
        'hosted_pro',
    ];

    public function getClientId(?int $storeId = null): string
    {
        return (string) $this->_getStoreConfig('maho_paypal/credentials/client_id', $storeId);
    }

    public function getClientSecret(?int $storeId = null): string
    {
        return (string) $this->_getStoreConfig('maho_paypal/credentials/client_secret', $storeId);
    }

    public function isSandbox(?int $storeId = null): bool
    {
        return (bool) $this->_getStoreConfig('maho_paypal/credentials/sandbox', $storeId);
    }

    public function isDebug(?int $storeId = null): bool
    {
        return (bool) $this->_getStoreConfig('maho_paypal/credentials/debug', $storeId);
    }

    public function getWebhookId(?int $storeId = null): string
    {
        return (string) $this->_getStoreConfig('maho_paypal/credentials/webhook_id', $storeId);
    }

    public function getJsSdkUrl(?int $storeId = null): string
    {
        return $this->isSandbox($storeId) ? self::JS_SDK_URL_SANDBOX : self::JS_SDK_URL_LIVE;
    }

    public function getNewPaymentAction(string $methodCode, ?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig("payment/{$methodCode}/payment_action", $storeId)
            ?: self::PAYMENT_ACTION_AUTHORIZE;
    }

    public function isNewMethodActive(string $methodCode, ?int $storeId = null): bool
    {
        return (bool) Mage::getStoreConfigFlag("payment/{$methodCode}/active", $storeId);
    }

    public function isVaultEnabled(?int $storeId = null): bool
    {
        return $this->isNewMethodActive(self::METHOD_VAULT, $storeId);
    }

    public function getClientTokenUrl(): string
    {
        return Mage::getUrl('paypal/checkout/clientToken', ['_secure' => true]);
    }

    public function isPayLaterMessagingEnabled(?int $storeId = null): bool
    {
        return (bool) Mage::getStoreConfigFlag('maho_paypal/general/paylater_messaging', $storeId)
            && $this->hasCredentials($storeId)
            && ($this->isNewMethodActive(self::METHOD_STANDARD_CHECKOUT, $storeId)
                || $this->isNewMethodActive(self::METHOD_ADVANCED_CHECKOUT, $storeId)
                || $this->isNewMethodActive(self::METHOD_VAULT, $storeId));
    }

    public function getCurrencyCode(?int $storeId = null): string
    {
        return Mage::app()->getStore($storeId)->getBaseCurrencyCode();
    }

    public function hasCredentials(?int $storeId = null): bool
    {
        return $this->getClientId($storeId) !== '' && $this->getClientSecret($storeId) !== '';
    }

    /**
     * Check if any deprecated legacy methods are active
     *
     * @return array<string> Active deprecated method codes
     */
    public function getActiveDeprecatedMethods(?int $storeId = null): array
    {
        $active = [];
        foreach (self::DEPRECATED_METHODS as $method) {
            if (Mage::getStoreConfigFlag("payment/{$method}/active", $storeId)) {
                $active[] = $method;
            }
        }
        return $active;
    }

    /**
     * Handle legacy Mage_Paypal method calls gracefully.
     * The old config had methods like isMethodAvailable(), isMethodActive(),
     * getMerchantCountry(), shouldAskToCreateBillingAgreement(), etc.
     * Return null so deprecated code paths silently disable themselves.
     *
     * TODO: Remove this method once Mage_Paypal is removed
     */
    #[\Override]
    public function __call($method, $args)
    {
        if (str_starts_with($method, 'is') || str_starts_with($method, 'should')) {
            return false;
        }
        if (str_starts_with($method, 'get') || str_starts_with($method, 'has')) {
            return null;
        }
        if (str_starts_with($method, 'set') || str_starts_with($method, 'uns')) {
            return $this;
        }
        return parent::__call($method, $args);
    }

    protected function _getStoreConfig(string $path, ?int $storeId = null): mixed
    {
        return Mage::getStoreConfig($path, $storeId);
    }
}
