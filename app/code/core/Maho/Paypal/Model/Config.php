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

    public function getClientId(?int $storeId = null): string
    {
        return (string) $this->_getStoreConfig('paypal/credentials/client_id', $storeId);
    }

    public function getClientSecret(?int $storeId = null): string
    {
        return (string) $this->_getStoreConfig('paypal/credentials/client_secret', $storeId);
    }

    public function isSandbox(?int $storeId = null): bool
    {
        return (bool) $this->_getStoreConfig('paypal/credentials/sandbox', $storeId);
    }

    public function isDebug(?int $storeId = null): bool
    {
        return (bool) $this->_getStoreConfig('paypal/credentials/debug', $storeId);
    }

    public function getWebhookId(?int $storeId = null): string
    {
        return (string) $this->_getStoreConfig('paypal/credentials/webhook_id', $storeId);
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

    public function isPayLaterEnabled(string $placement, ?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('paypal/general/paylater_enabled', $storeId)
            && Mage::getStoreConfigFlag("paypal/general/paylater_{$placement}", $storeId)
            && $this->hasCredentials($storeId)
            && ($this->isNewMethodActive(self::METHOD_STANDARD_CHECKOUT, $storeId)
                || $this->isNewMethodActive(self::METHOD_ADVANCED_CHECKOUT, $storeId)
                || $this->isNewMethodActive(self::METHOD_VAULT, $storeId));
    }

    public function isPayLaterMessagingEnabled(?int $storeId = null): bool
    {
        return $this->isPayLaterEnabled('product', $storeId)
            || $this->isPayLaterEnabled('cart', $storeId);
    }

    public function getCurrencyCode(?int $storeId = null): string
    {
        return Mage::app()->getStore($storeId)->getBaseCurrencyCode();
    }

    public function hasCredentials(?int $storeId = null): bool
    {
        return $this->getClientId($storeId) !== '' && $this->getClientSecret($storeId) !== '';
    }

    protected function _getStoreConfig(string $path, ?int $storeId = null): mixed
    {
        return Mage::getStoreConfig($path, $storeId);
    }
}
