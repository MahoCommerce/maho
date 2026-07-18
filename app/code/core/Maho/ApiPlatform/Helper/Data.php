<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_PROTOCOL_PREFIX = 'apiplatform/protocols/';
    public const XML_PATH_TOKEN_LIFETIME = 'apiplatform/oauth2/token_lifetime';
    public const XML_PATH_REFRESH_TOKEN_LIFETIME = 'apiplatform/oauth2/refresh_token_lifetime';
    public const DEFAULT_TOKEN_LIFETIME = 3600;
    public const DEFAULT_REFRESH_TOKEN_LIFETIME = 86400;

    public const PROTOCOL_REST_V2 = 'rest_v2';
    public const PROTOCOL_GRAPHQL = 'graphql';
    public const PROTOCOL_ADMIN_GRAPHQL = 'admin_graphql';
    public const PROTOCOL_LEGACY_REST = 'legacy_rest';
    public const PROTOCOL_SOAP = 'soap';
    public const PROTOCOL_V2_SOAP = 'v2_soap';
    public const PROTOCOL_XMLRPC = 'xmlrpc';
    public const PROTOCOL_JSONRPC = 'jsonrpc';

    /**
     * Check whether a specific API protocol is enabled.
     *
     * Protocols are opt-in: every value defaults to 0 in config.xml. Operators
     * must explicitly enable each protocol they want exposed via System > Config
     * > Services > API Platform > API Protocols.
     */
    public function isProtocolEnabled(string $protocol): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_PROTOCOL_PREFIX . $protocol);
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
