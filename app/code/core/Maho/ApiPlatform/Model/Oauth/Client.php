<?php

/**
 * An OAuth 2.1 client, either registered dynamically or created by an admin.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Oauth_Client extends Mage_Core_Model_Abstract
{
    public const AUTH_METHOD_NONE = 'none';
    public const AUTH_METHOD_CLIENT_SECRET_POST = 'client_secret_post';
    public const AUTH_METHOD_CLIENT_SECRET_BASIC = 'client_secret_basic';

    public const GRANT_AUTHORIZATION_CODE = 'authorization_code';
    public const GRANT_REFRESH_TOKEN = 'refresh_token';

    /** RFC 8252: a native client redirects to loopback on a port it picks at runtime. */
    protected const LOOPBACK_HOSTS = ['127.0.0.1', '[::1]', 'localhost'];

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/oauth_client');
    }

    public function loadByClientId(string $clientId): self
    {
        $this->load($clientId, 'client_id');
        return $this;
    }

    public function isPublic(): bool
    {
        return (string) $this->getData('client_secret_hash') === '';
    }

    public function isTrusted(): bool
    {
        return (bool) $this->getData('is_trusted');
    }

    /**
     * @return list<string>
     */
    public function getRedirectUris(): array
    {
        $raw = (string) $this->getData('redirect_uris');
        if ($raw === '') {
            return [];
        }

        try {
            $uris = Mage::helper('core')->jsonDecode($raw);
        } catch (JsonException) {
            return [];
        }

        return is_array($uris) ? array_values(array_filter($uris, is_string(...))) : [];
    }

    /**
     * @param list<string> $uris
     */
    public function setRedirectUris(array $uris): self
    {
        $this->setData('redirect_uris', Mage::helper('core')->jsonEncode(array_values($uris)));
        return $this;
    }

    /**
     * Exact string match, as the MCP specification requires. Prefix and wildcard
     * matching are what open-redirect attacks are built on.
     */
    public function hasRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->getRedirectUris(), true);
    }

    /**
     * @return list<string>
     */
    public function getGrantTypes(): array
    {
        $raw = trim((string) $this->getData('grant_types'));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $raw))));
    }

    public function hasGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->getGrantTypes(), true);
    }

    public function verifySecret(#[\SensitiveParameter] string $secret): bool
    {
        $hash = (string) $this->getData('client_secret_hash');
        if ($hash === '') {
            return false;
        }

        return password_verify($secret, $hash);
    }

    public function recordUsage(): self
    {
        $now = Mage_Core_Model_Locale::nowUtc();
        $this->resource()->touchLastUsedAt((int) $this->getId(), $now);
        $this->setData('last_used_at', $now);
        return $this;
    }

    protected function resource(): Maho_ApiPlatform_Model_Resource_Oauth_Client
    {
        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Client $resource */
        $resource = $this->getResource();
        return $resource;
    }

    /**
     * A redirect URI must be HTTPS, or loopback on any port. Anything else lets
     * an authorization code leave over the network in clear text. When the store
     * lists allowed redirect hosts, an HTTPS URI must use one of them.
     */
    public static function isAllowedRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || isset($parts['fragment'])) {
            return false;
        }

        // With a user part or a backslash, a browser can go to a host other than the one parse_url() returns.
        if (isset($parts['user']) || str_contains($uri, '\\')) {
            return false;
        }

        $host = strtolower($parts['host']);
        if (in_array($host, self::LOOPBACK_HOSTS, true)) {
            return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        $allowedHosts = $helper->getAllowedRedirectHosts();

        return $allowedHosts === [] || in_array($host, $allowedHosts, true);
    }

    /**
     * A loopback redirect sends the code to the computer of the admin, so a link
     * from another person cannot get the code.
     */
    public static function isLoopbackRedirectUri(string $uri): bool
    {
        return self::isAllowedRedirectUri($uri)
            && in_array(strtolower((string) parse_url($uri, PHP_URL_HOST)), self::LOOPBACK_HOSTS, true);
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getId()) {
            $this->setData('created_at', Mage_Core_Model_Locale::nowUtc());
        }

        return parent::_beforeSave();
    }
}
