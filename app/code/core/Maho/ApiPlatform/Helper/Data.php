<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API Platform Helper
 */
class Maho_ApiPlatform_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'maho_apiplatform/general/enabled';
    public const XML_PATH_LEGACY_SUNSET_DATE = 'maho_apiplatform/general/legacy_sunset_date';
    public const XML_PATH_TOKEN_LIFETIME = 'maho_apiplatform/oauth2/token_lifetime';
    public const XML_PATH_REFRESH_TOKEN_LIFETIME = 'maho_apiplatform/oauth2/refresh_token_lifetime';
    public const DEFAULT_LEGACY_SUNSET_DATE = '2028-01-01';
    public const DEFAULT_TOKEN_LIFETIME = 3600;
    public const DEFAULT_REFRESH_TOKEN_LIFETIME = 86400;

    /**
     * Check if API Platform is enabled
     */
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Get the sunset date for legacy APIs (SOAP/REST)
     */
    public function getLegacySunsetDate(): string
    {
        return Mage::getStoreConfig(self::XML_PATH_LEGACY_SUNSET_DATE)
            ?: self::DEFAULT_LEGACY_SUNSET_DATE;
    }

    /**
     * Get OAuth2 access token lifetime in seconds
     */
    public function getTokenLifetime(): int
    {
        $value = Mage::getStoreConfig(self::XML_PATH_TOKEN_LIFETIME);
        return $value !== null ? (int) $value : self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Get OAuth2 refresh token lifetime in seconds
     */
    public function getRefreshTokenLifetime(): int
    {
        $value = Mage::getStoreConfig(self::XML_PATH_REFRESH_TOKEN_LIFETIME);
        return $value !== null ? (int) $value : self::DEFAULT_REFRESH_TOKEN_LIFETIME;
    }

    /**
     * Get captcha configuration for the frontend.
     * Dispatches api_captcha_config so any captcha module can describe itself.
     */
    public function getCaptchaConfig(): array
    {
        $config = new \Maho\DataObject(['enabled' => false]);
        Mage::dispatchEvent('api_captcha_config', [
            'config' => $config,
        ]);
        return $config->getData();
    }

    /**
     * Verify a captcha token submitted via the API.
     * Returns null on success, or an error message string on failure.
     */
    public function verifyCaptcha(array $data): ?string
    {
        $result = new \Maho\DataObject(['verified' => true, 'error' => '']);
        Mage::dispatchEvent('api_verify_captcha', [
            'result' => $result,
            'data' => $data,
        ]);

        if (!$result->getVerified()) {
            return $result->getError() ?: Mage::helper('captcha')->__('Incorrect CAPTCHA.');
        }

        return null;
    }

}
