<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * TODO: Remove @method and @property annotations once Mage_Paypal is removed.
 * These exist only for backward compatibility with legacy Mage_Paypal code
 * that calls methods/properties on the config object via __call()/__get().
 *
 * @method bool isMethodActive(string $method)
 * @method bool isMethodAvailable(string|null $method = null)
 * @method bool shouldAskToCreateBillingAgreement()
 * @method string|null getPaymentMarkWhatIsPaypalUrl(mixed ...$args)
 * @method string|null getPaymentMarkImageUrl(mixed ...$args)
 * @method $this setMethod(string $method)
 * @method $this setStoreId(int $storeId)
 *
 * @property bool $visible_on_cart
 * @property bool $visible_on_product
 * @property bool $sandboxFlag
 * @property string|null $businessAccount
 */
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
     * TODO: Remove once Mage_Paypal is removed
     *
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

    /**
     * TODO: Remove once Mage_Paypal is removed
     */
    private ?Mage_Paypal_Model_Config $_legacyConfig = null;

    /**
     * TODO: Remove once Mage_Paypal is removed
     */
    private array $_legacyConstructParams = [];

    public function __construct(array $data = [])
    {
        // The old Mage_Paypal_Model_Config constructor expects [$methodCode, $storeId]
        // positionally, while DataObject expects key-value pairs. Capture the raw
        // params for forwarding to the legacy config.
        if ($data && array_is_list($data)) {
            $this->_legacyConstructParams = $data;
            $data = [];
        }
        parent::__construct($data);
    }

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

    public function isPayLaterEnabled(string $placement, ?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('maho_paypal/general/paylater_enabled', $storeId)
            && Mage::getStoreConfigFlag("maho_paypal/general/paylater_{$placement}", $storeId)
            && $this->hasCredentials($storeId)
            && ($this->isNewMethodActive(self::METHOD_STANDARD_CHECKOUT, $storeId)
                || $this->isNewMethodActive(self::METHOD_ADVANCED_CHECKOUT, $storeId)
                || $this->isNewMethodActive(self::METHOD_VAULT, $storeId));
    }

    public function isPayLaterMessagingEnabled(?int $storeId = null): bool
    {
        return $this->isPayLaterEnabled('product', $storeId)
            || $this->isPayLaterEnabled('cart', $storeId)
            || $this->isPayLaterEnabled('checkout', $storeId);
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
     * TODO: Remove once Mage_Paypal is removed
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
     * Delegate legacy Mage_Paypal method calls to the old config instance.
     *
     * TODO: Remove this method once Mage_Paypal is removed
     */
    #[\Override]
    public function __call($method, $args)
    {
        $legacy = $this->_getLegacyConfig();
        if (method_exists($legacy, $method)) {
            $result = $legacy->$method(...$args);
            // Return $this instead of the legacy instance for fluent setters
            return $result === $legacy ? $this : $result;
        }
        return parent::__call($method, $args);
    }

    /**
     * Delegate legacy property access to the old config instance.
     *
     * TODO: Remove this method once Mage_Paypal is removed
     */
    #[\Override]
    public function __get($key)
    {
        $legacy = $this->_getLegacyConfig();
        if (property_exists($legacy, $key)) {
            return $legacy->$key;
        }
        return parent::__get($key);
    }

    /**
     * TODO: Remove once Mage_Paypal is removed
     */
    private function _getLegacyConfig(): Mage_Paypal_Model_Config
    {
        if ($this->_legacyConfig === null) {
            $this->_legacyConfig = new Mage_Paypal_Model_Config($this->_legacyConstructParams);
        }
        return $this->_legacyConfig;
    }

    protected function _getStoreConfig(string $path, ?int $storeId = null): mixed
    {
        return Mage::getStoreConfig($path, $storeId);
    }
}
