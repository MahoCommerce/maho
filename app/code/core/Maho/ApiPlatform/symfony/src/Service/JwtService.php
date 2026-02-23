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

namespace Maho\ApiPlatform\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT Service - Centralized JWT token management
 *
 * Consolidates JWT generation and validation logic from AuthController
 * and OAuth2Authenticator to ensure consistent behavior across the API.
 */
class JwtService
{
    private const CONFIG_PATH_SECRET = 'maho_apiplatform/oauth2/secret';
    private const CONFIG_PATH_LEGACY = 'maho_api/settings/jwt_secret';
    private const TOKEN_EXPIRY_SECONDS = 86400; // 24 hours
    private const ALGORITHM = 'HS256';
    private const AUDIENCE = 'maho-api';

    private ?string $cachedSecret = null;

    /**
     * Generate JWT token for a customer
     *
     * @return string The JWT token
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function generateCustomerToken(\Mage_Customer_Model_Customer $customer): string
    {
        $secret = $this->getSecret();
        $now = time();

        $payload = [
            'iss' => $this->getIssuer(),
            'aud' => self::AUDIENCE,
            'jti' => bin2hex(random_bytes(16)),
            'sub' => 'customer_' . $customer->getId(),
            'iat' => $now,
            'exp' => $now + self::TOKEN_EXPIRY_SECONDS,
            'customer_id' => (int) $customer->getId(),
            'email' => $customer->getEmail(),
            'type' => 'customer',
            'roles' => ['ROLE_USER'],
        ];

        return JWT::encode($payload, $secret, self::ALGORITHM);
    }

    /**
     * Generate JWT token for an admin user
     *
     * @return string The JWT token
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function generateAdminToken(\Mage_Admin_Model_User $admin): string
    {
        $secret = $this->getSecret();
        $now = time();

        $payload = [
            'iss' => $this->getIssuer(),
            'aud' => self::AUDIENCE,
            'jti' => bin2hex(random_bytes(16)),
            'sub' => 'admin_' . $admin->getId(),
            'iat' => $now,
            'exp' => $now + self::TOKEN_EXPIRY_SECONDS,
            'admin_id' => (int) $admin->getId(),
            'email' => $admin->getEmail(),
            'type' => 'admin',
            'roles' => ['ROLE_ADMIN'],
        ];

        return JWT::encode($payload, $secret, self::ALGORITHM);
    }

    /**
     * Generate JWT token for a dedicated API user
     *
     * @param array<string> $permissions Resource permissions from api_rule
     * @return string The JWT token
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function generateApiUserToken(\Mage_Api_Model_User $apiUser, array $permissions = []): string
    {
        $secret = $this->getSecret();
        $now = time();

        $payload = [
            'iss' => $this->getIssuer(),
            'aud' => self::AUDIENCE,
            'jti' => bin2hex(random_bytes(16)),
            'sub' => 'api_user_' . $apiUser->getId(),
            'iat' => $now,
            'exp' => $now + $this->getTokenExpiry(),
            'api_user_id' => (int) $apiUser->getId(),
            'username' => $apiUser->getUsername(),
            'type' => 'api_user',
            'roles' => ['ROLE_API_USER'],
            'permissions' => $permissions,
        ];

        return JWT::encode($payload, $secret, self::ALGORITHM);
    }

    /**
     * Load permissions for an API user from api_role + api_rule tables
     *
     * @return array<string> e.g. ['orders/read', 'shipments/write', 'products/all']
     */
    public function loadApiUserPermissions(\Mage_Api_Model_User $apiUser): array
    {
        $permissions = [];
        $roleIds = $apiUser->getRoles();

        if (empty($roleIds)) {
            return $permissions;
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $ruleTable = $resource->getTableName('api/rule');

        foreach ($roleIds as $roleId) {
            $rules = $read->fetchAll(
                $read->select()
                    ->from($ruleTable, ['resource_id', 'api_permission'])
                    ->where('role_id = ?', $roleId)
                    ->where('role_type = ?', 'G')
                    ->where('api_permission = ?', 'allow'),
            );

            foreach ($rules as $rule) {
                $resourceId = $rule['resource_id'];
                if ($resourceId === 'all') {
                    return ['all'];
                }
                $permissions[] = $resourceId;
            }
        }

        return array_unique($permissions);
    }

    /**
     * Decode and validate a JWT token
     *
     * @param string $token The JWT token to decode
     * @return object The decoded payload
     * @throws \Firebase\JWT\ExpiredException If token is expired
     * @throws \Firebase\JWT\SignatureInvalidException If signature is invalid
     * @throws \UnexpectedValueException If token format is invalid
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function decodeToken(string $token): object
    {
        $secret = $this->getSecret();
        $payload = JWT::decode($token, new Key($secret, self::ALGORITHM));

        // Validate audience
        if (($payload->aud ?? null) !== self::AUDIENCE) {
            throw new \UnexpectedValueException('Invalid token audience');
        }

        // Validate issuer
        if (($payload->iss ?? null) !== $this->getIssuer()) {
            throw new \UnexpectedValueException('Invalid token issuer');
        }

        return $payload;
    }

    /**
     * Check if a token is valid without throwing exceptions
     *
     * @param string $token The JWT token to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidToken(string $token): bool
    {
        try {
            $this->decodeToken($token);
            return true;
        } catch (\Exception $e) {
            \Mage::log('JWT validation failed: ' . $e->getMessage(), \Mage::LOG_DEBUG, 'api_auth.log');
            return false;
        }
    }

    /**
     * Extract customer ID from token if present
     *
     * @param string $token The JWT token
     * @return int|null Customer ID or null if not a customer token
     */
    public function getCustomerIdFromToken(string $token): ?int
    {
        try {
            $payload = $this->decodeToken($token);
            return isset($payload->customer_id) ? (int) $payload->customer_id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract admin ID from token if present
     *
     * @param string $token The JWT token
     * @return int|null Admin ID or null if not an admin token
     */
    public function getAdminIdFromToken(string $token): ?int
    {
        try {
            $payload = $this->decodeToken($token);
            return isset($payload->admin_id) ? (int) $payload->admin_id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get JWT secret from Maho configuration
     *
     * @return string The JWT secret
     * @throws \RuntimeException If secret is not configured or too short
     */
    public function getSecret(): string
    {
        if ($this->cachedSecret !== null) {
            return $this->cachedSecret;
        }

        // Try API Platform specific secret first
        $secret = \Mage::getStoreConfig(self::CONFIG_PATH_SECRET);

        // Fall back to legacy API JWT secret
        if (empty($secret)) {
            $secret = \Mage::getStoreConfig(self::CONFIG_PATH_LEGACY);
        }

        if (empty($secret)) {
            throw new \RuntimeException('JWT secret not configured. Please set maho_apiplatform/oauth2/secret in configuration.');
        }

        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT secret must be at least 32 characters. Configure in System > Configuration > API > JWT.');
        }

        $this->cachedSecret = $secret;
        return $secret;
    }

    /**
     * Get token expiry in seconds
     */
    public function getTokenExpiry(): int
    {
        return self::TOKEN_EXPIRY_SECONDS;
    }

    /**
     * Get the issuer URL for tokens
     */
    public function getIssuer(): string
    {
        return rtrim((string) \Mage::getStoreConfig('web/unsecure/base_url'), '/') . '/';
    }
}
