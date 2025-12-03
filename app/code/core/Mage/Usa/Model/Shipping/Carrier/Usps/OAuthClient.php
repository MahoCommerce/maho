<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient
{
    private const TOKEN_CACHE_KEY_PREFIX = 'usps_oauth_token_';

    private string $clientId;
    private string $clientSecret;
    private string $tokenEndpoint;
    private Mage_Core_Model_Cache $cache;

    public function __construct(string $clientId, string $clientSecret, string $baseUrl)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tokenEndpoint = $baseUrl . '/oauth2/v3/token';
        $this->cache = Mage::app()->getCache();
    }

    /**
     * Get valid access token (from cache or fetch new)
     */
    public function getAccessToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . md5($this->clientId . $this->tokenEndpoint);
        $cachedToken = $this->cache->load($cacheKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        return $this->fetchNewToken();
    }

    /**
     * Fetch new OAuth token using client credentials flow
     */
    private function fetchNewToken(): string
    {
        $client = \Symfony\Component\HttpClient\HttpClient::create([
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

        // Cache token for slightly less than expiry time (5 min buffer)
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . md5($this->clientId . $this->tokenEndpoint);
        $this->cache->save(
            $accessToken,
            $cacheKey,
            ['usps_oauth'],
            max(60, $expiresIn - 300),
        );

        return $accessToken;
    }
}
