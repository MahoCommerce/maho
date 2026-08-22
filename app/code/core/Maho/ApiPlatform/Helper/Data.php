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
    public const XML_PATH_AUTHORIZATION_ENABLED = 'apiplatform/oauth2/authorization_enabled';
    public const XML_PATH_DYNAMIC_REGISTRATION = 'apiplatform/oauth2/dynamic_registration_enabled';
    public const XML_PATH_CODE_LIFETIME = 'apiplatform/oauth2/authorization_code_lifetime';
    public const XML_PATH_MCP_REQUIRE_AUTH = 'apiplatform/oauth2/mcp_require_auth';
    public const DEFAULT_TOKEN_LIFETIME = 3600;
    public const DEFAULT_REFRESH_TOKEN_LIFETIME = 86400;
    public const DEFAULT_CODE_LIFETIME = 60;

    public const PROTOCOL_REST_V2 = 'rest_v2';
    public const PROTOCOL_GRAPHQL = 'graphql';
    public const PROTOCOL_ADMIN_GRAPHQL = 'admin_graphql';
    public const PROTOCOL_MCP = 'mcp';
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
     * The MCP server needs both the toggle and the optional bundle behind it: without the bundle
     * the kernel never registers the /api/mcp path at all.
     */
    public function isMcpEnabled(): bool
    {
        return $this->isProtocolEnabled(self::PROTOCOL_MCP) && $this->isMcpAvailable();
    }

    public function isMcpAvailable(): bool
    {
        return class_exists(\Symfony\AI\McpBundle\McpBundle::class)
            && \Composer\InstalledVersions::isInstalled('psr/http-factory-implementation');
    }

    /**
     * Whether any bearer-authenticated API is reachable, which is what the discovery documents
     * under /.well-known describe.
     */
    public function hasPublicApi(): bool
    {
        return $this->isProtocolEnabled(self::PROTOCOL_REST_V2)
            || $this->isProtocolEnabled(self::PROTOCOL_GRAPHQL)
            || $this->isMcpEnabled();
    }

    /**
     * Root of the current domain, with the trailing slash. /api and /.well-known live there, above
     * any store code in the path.
     */
    public function getRootUrl(): string
    {
        return rtrim(Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/') . '/';
    }

    /**
     * The RFC 9728 challenge sent with a 401, which is how a client finds out how to authenticate
     * without being told out of band.
     */
    public function getBearerChallenge(): string
    {
        return 'Bearer resource_metadata="' . $this->getRootUrl()
            . Maho_ApiPlatform_Model_Discovery::PATH_PROTECTED_RESOURCE . '"';
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
     * The authorization server only runs when there is something for it to protect.
     */
    public function isAuthorizationServerEnabled(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_AUTHORIZATION_ENABLED) && $this->hasPublicApi();
    }

    public function isDynamicRegistrationEnabled(): bool
    {
        return $this->isAuthorizationServerEnabled()
            && Mage::getStoreConfigFlag(self::XML_PATH_DYNAMIC_REGISTRATION);
    }

    /**
     * Whether /api/mcp challenges an unauthenticated caller instead of serving
     * the public tools.
     *
     * Off by default: anonymous catalog browsing is a real use for MCP and it
     * works today. A store turns this on when it wants a client to authenticate
     * while connecting, which is the only moment some clients will do it.
     */
    public function isMcpAuthRequired(): bool
    {
        return $this->isAuthorizationServerEnabled()
            && Mage::getStoreConfigFlag(self::XML_PATH_MCP_REQUIRE_AUTH);
    }

    public function getAuthorizationCodeLifetime(): int
    {
        $value = (int) Mage::getStoreConfig(self::XML_PATH_CODE_LIFETIME);
        return $value > 0 ? $value : self::DEFAULT_CODE_LIFETIME;
    }

    /**
     * Every host root this install answers on, without a trailing slash.
     *
     * /api and /.well-known sit above the store code in the path, so they belong
     * to the host rather than to one store. A multi-store install can serve
     * several domains, and a token minted on one of them must still validate
     * there, so the set covers all of them rather than only the current store.
     *
     * @return non-empty-list<string>
     */
    public function getBaseRoots(): array
    {
        $roots = [rtrim($this->getRootUrl(), '/')];

        foreach (Mage::app()->getStores(true) as $store) {
            foreach ([Mage_Core_Model_Store::URL_TYPE_WEB] as $type) {
                $roots[] = rtrim($store->getBaseUrl($type), '/');
            }
        }

        return array_values(array_unique(array_filter($roots)));
    }

    /**
     * The resource identifiers a token may be bound to, per RFC 8707. The MCP
     * endpoint is listed separately from the root so a client can ask for the
     * narrowest audience it can use, and so a token minted for MCP cannot be
     * replayed against the rest of the API.
     *
     * Canonical form carries no trailing slash and no fragment.
     *
     * @return non-empty-list<string>
     */
    public function getCanonicalResources(): array
    {
        $resources = [];

        foreach ($this->getBaseRoots() as $root) {
            $resources[] = $root;
            if ($this->isMcpEnabled()) {
                $resources[] = $root . '/api/mcp';
            }
        }

        return $resources;
    }

    /**
     * The narrowest resource for the current request: the MCP endpoint when it
     * is enabled, otherwise the host root. This is what a token gets when the
     * client sends no `resource` parameter.
     */
    public function getDefaultResource(): string
    {
        $root = rtrim($this->getRootUrl(), '/');

        return $this->isMcpEnabled() ? $root . '/api/mcp' : $root;
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
