<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Maho_SocialLogin_Model_Provider_Facebook implements Maho_SocialLogin_Model_Provider_ProviderInterface
{
    private const GRAPH_API_URL = 'https://graph.facebook.com/v19.0';

    private ?HttpClientInterface $httpClient = null;

    #[\Override]
    public function getCode(): string
    {
        return 'facebook';
    }

    #[\Override]
    public function isEnabled(int $storeId): bool
    {
        return Mage::helper('sociallogin')->isFacebookEnabled($storeId);
    }

    #[\Override]
    public function requiresNonce(): bool
    {
        return false;
    }

    public function setHttpClient(HttpClientInterface $client): void
    {
        $this->httpClient = $client;
    }

    protected function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient ??= HttpClient::create(['timeout' => 10]);
    }

    /**
     * Facebook access tokens carry no nonce claim; replay is bounded by the
     * app-secret debug_token check and the token's own expiry, so
     * $expectedNonce is ignored.
     */
    #[\Override]
    public function verify(#[\SensitiveParameter] string $token, int $storeId, ?string $expectedNonce = null): array
    {
        $helper = Mage::helper('sociallogin');
        $appId = $helper->getFacebookAppId($storeId);
        $appSecret = $helper->getFacebookAppSecret($storeId);
        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('Facebook app credentials are not configured');
        }

        $debug = $this->fetchJson('/debug_token', [
            'input_token' => $token,
            'access_token' => $appId . '|' . $appSecret,
        ]);
        $debugData = is_array($debug['data'] ?? null) ? $debug['data'] : [];
        if (empty($debugData['is_valid'])) {
            throw new InvalidArgumentException('Facebook token is not valid');
        }
        if (((string) ($debugData['app_id'] ?? '')) !== $appId) {
            throw new InvalidArgumentException('Facebook token was issued for another app');
        }
        $expiresAt = (int) ($debugData['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            throw new InvalidArgumentException('Facebook token has expired');
        }
        $userId = (string) ($debugData['user_id'] ?? '');
        if ($userId === '') {
            throw new InvalidArgumentException('Facebook token has no user');
        }

        $profile = $this->fetchJson('/me', [
            'fields' => 'id,email,first_name,last_name,name',
            'access_token' => $token,
        ]);
        if (((string) ($profile['id'] ?? '')) !== $userId) {
            throw new InvalidArgumentException('Facebook profile does not match the token');
        }
        // The Graph API returns an email only after Facebook has confirmed it,
        // so its presence is the provider's verification signal.
        $email = $profile['email'] ?? null;
        if (!is_string($email) || $email === '') {
            throw new InvalidArgumentException('Email not available from Facebook');
        }

        return [
            'sub' => $userId,
            'email' => strtolower($email),
            'given_name' => is_string($profile['first_name'] ?? null) && $profile['first_name'] !== '' ? $profile['first_name'] : null,
            'family_name' => is_string($profile['last_name'] ?? null) && $profile['last_name'] !== '' ? $profile['last_name'] : null,
            'name' => is_string($profile['name'] ?? null) && $profile['name'] !== '' ? $profile['name'] : null,
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    protected function fetchJson(string $path, array $query): array
    {
        try {
            $response = $this->getHttpClient()->request('GET', self::GRAPH_API_URL . $path, ['query' => $query]);
            $status = $response->getStatusCode();
            $body = json_decode($response->getContent(false), true);
        } catch (Throwable $e) {
            throw new RuntimeException('Facebook Graph API is unreachable', 0, $e);
        }

        if ($status >= 500 || !is_array($body)) {
            throw new RuntimeException("Facebook Graph API returned an unusable response for {$path}");
        }
        if ($status >= 400) {
            throw new InvalidArgumentException('Facebook rejected the token');
        }

        return $body;
    }
}
