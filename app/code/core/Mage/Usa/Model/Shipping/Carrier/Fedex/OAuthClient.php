<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient
{
    private const TOKEN_CACHE_KEY_PREFIX = 'fedex_oauth_token_';

    public const CACHE_TAG = 'fedex_oauth';
    private string $tokenEndpoint;
    private Mage_Core_Model_Cache $cache;

    public function __construct(private string $clientId, private string $clientSecret, string $baseUrl)
    {
        $this->tokenEndpoint = $baseUrl . '/oauth/token';
        $this->cache = Mage::app()->getCache();
    }

    /**
     * Get valid access token (from cache or fetch new)
     */
    public function getAccessToken(): string
    {
        $cachedToken = $this->cache->load($this->getCacheKey());

        if ($cachedToken) {
            return $cachedToken;
        }

        return $this->fetchNewToken();
    }

    /**
     * Fetch new OAuth token using client credentials flow.
     *
     * FedEx serves its token endpoint as application/x-www-form-urlencoded; a JSON
     * body is rejected, unlike the USPS equivalent.
     */
    private function fetchNewToken(): string
    {
        $client = \Symfony\Component\HttpClient\HttpClient::create([
            'timeout' => 10,
        ]);
        $response = $client->request('POST', $this->tokenEndpoint, [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = Mage::helper('core')->jsonDecode($response->getContent());
        $accessToken = $data['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('FedEx OAuth response contains no access token');
        }
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        $this->cache->save(
            $accessToken,
            $this->getCacheKey(),
            [self::CACHE_TAG],
            max(60, $expiresIn - 300),
        );

        return $accessToken;
    }

    public function invalidateToken(): void
    {
        $this->cache->remove($this->getCacheKey());
    }

    private function getCacheKey(): string
    {
        // The secret is part of the key so saving a corrected secret stops serving
        // the token minted with the old one
        return self::TOKEN_CACHE_KEY_PREFIX . md5($this->clientId . $this->clientSecret . $this->tokenEndpoint);
    }
}
