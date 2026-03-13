<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Config extends Mage_Paypal_Model_Config
{
    public const METHOD_STANDARD_CHECKOUT = 'paypal_standard_checkout';
    public const METHOD_ADVANCED_CHECKOUT = 'paypal_advanced_checkout';
    public const METHOD_VAULT = 'paypal_vault';

    public const PAYMENT_ACTION_AUTHORIZE = 'authorize';
    public const PAYMENT_ACTION_CAPTURE = 'capture';

    public const JS_SDK_URL_SANDBOX = 'https://www.sandbox.paypal.com/sdk/js';
    public const JS_SDK_URL_LIVE = 'https://www.paypal.com/sdk/js';

    /**
     * @deprecated Legacy PayPal methods - use new Maho_Paypal methods instead
     */
    public const DEPRECATED_METHODS = [
        self::METHOD_WPP_EXPRESS,
        self::METHOD_WPP_DIRECT,
        self::METHOD_WPS,
        self::METHOD_WPP_PE_EXPRESS,
        self::METHOD_WPP_PE_DIRECT,
        self::METHOD_PAYFLOWPRO,
        self::METHOD_PAYFLOWLINK,
        self::METHOD_PAYFLOWADVANCED,
        self::METHOD_HOSTEDPRO,
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

    public function getJsSdkUrl(string $methodCode, ?int $storeId = null): string
    {
        $baseUrl = $this->isSandbox($storeId) ? self::JS_SDK_URL_SANDBOX : self::JS_SDK_URL_LIVE;
        $clientId = $this->getClientId($storeId);

        $params = [
            'client-id' => $clientId,
            'currency' => Mage::app()->getStore($storeId)->getCurrentCurrencyCode(),
        ];

        $intent = $this->getNewPaymentAction($methodCode, $storeId);
        if ($intent === self::PAYMENT_ACTION_AUTHORIZE) {
            $params['intent'] = 'authorize';
        } else {
            $params['intent'] = 'capture';
        }

        if ($methodCode === self::METHOD_ADVANCED_CHECKOUT) {
            $params['components'] = 'card-fields';
        } elseif ($methodCode === self::METHOD_STANDARD_CHECKOUT) {
            $params['components'] = 'buttons';
        }

        if ($methodCode === self::METHOD_VAULT || $this->isVaultEnabled($storeId)) {
            $params['components'] = ($params['components'] ?? '') . ',vault';
            $params['components'] = ltrim($params['components'], ',');
        }

        if ($methodCode === self::METHOD_STANDARD_CHECKOUT) {
            $disableFunding = $this->getDisabledFundingSources($storeId);
            if ($disableFunding) {
                $params['disable-funding'] = $disableFunding;
            }
        }

        return $baseUrl . '?' . http_build_query($params);
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

    public function getDisabledFundingSources(?int $storeId = null): string
    {
        $sources = array_filter(explode(
            ',',
            (string) $this->_getStoreConfig('payment/paypal_standard_checkout/disable_funding', $storeId),
        ));

        if ($this->isNewMethodActive(self::METHOD_ADVANCED_CHECKOUT, $storeId) && !in_array('card', $sources)) {
            $sources[] = 'card';
        }

        return implode(',', $sources);
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

    protected function _getStoreConfig(string $path, ?int $storeId = null): mixed
    {
        return Mage::getStoreConfig($path, $storeId);
    }
}
