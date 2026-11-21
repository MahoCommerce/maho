<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient
{
    private const TOKEN_CACHE_KEY_PREFIX = 'usps_oauth_token_';
    private string $tokenEndpoint;
    private Mage_Core_Model_Cache $cache;

    public function __construct(private string $clientId, private string $clientSecret, string $baseUrl)
    {
        $this->tokenEndpoint = $baseUrl . '/oauth2/v3/token';
        $this->cache = Mage::app()->getCache();
    }

    /**
     * Get valid access token (from cache or fetch new)
     */
    public function getAccessToken(): string
    {
        $cacheKey = $this->getCacheKey();
        $cachedToken = $this->cache->load($cacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        $result = $this->fetchNewToken();
        $this->cache->save(
            $result['access_token'],
            $cacheKey,
            ['usps_oauth'],
            max(60, $result['expires_in'] - 300),
        );

        return $result['access_token'];
    }

    public function clearAccessToken(): void
    {
        $this->cache->remove($this->getCacheKey());
    }

    /**
     * Fetch new OAuth token using client credentials flow
     */
    /**
     * @return array{access_token: string, expires_in: int}
     */
    protected function fetchNewToken(): array
    {
        $client = \Maho\Http\Client::create([
            'timeout' => 10,  // OAuth endpoints should respond quickly
        ]);
        $response = $client->request('POST', $this->tokenEndpoint, [
            'json' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $data = Mage::helper('core')->jsonDecode($response->getContent());
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 28800; // Default 8 hours

        return [
            'access_token' => (string) $accessToken,
            'expires_in' => (int) $expiresIn,
        ];
    }

    private function getCacheKey(): string
    {
        return self::TOKEN_CACHE_KEY_PREFIX . hash(
            'sha256',
            $this->clientId . "\0" . $this->clientSecret . "\0" . $this->tokenEndpoint,
        );
    }
}
