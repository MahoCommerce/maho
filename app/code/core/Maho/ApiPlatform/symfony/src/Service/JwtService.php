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

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\Clock\SystemClock;

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
    private const AUDIENCE = 'maho-api';

    private ?string $cachedSecret = null;
    private ?Configuration $config = null;

    private function getConfig(): Configuration
    {
        if ($this->config === null) {
            $this->config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->getSecret()),
            );
        }
        return $this->config;
    }

    /**
     * Generate JWT token for a customer
     *
     * @return string The JWT token
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function generateCustomerToken(\Mage_Customer_Model_Customer $customer): string
    {
        $now = new DateTimeImmutable();
        $config = $this->getConfig();

        $token = $config->builder()
            ->issuedBy($this->getIssuer())
            ->permittedFor(self::AUDIENCE)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('customer_' . $customer->getId())
            ->issuedAt($now)
            ->expiresAt($now->modify('+' . self::TOKEN_EXPIRY_SECONDS . ' seconds'))
            ->withClaim('customer_id', (int) $customer->getId())
            ->withClaim('email', $customer->getEmail())
            ->withClaim('type', 'customer')
            ->withClaim('roles', ['ROLE_USER'])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    /**
     * Generate JWT token for an admin user
     *
     * @return string The JWT token
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function generateAdminToken(\Mage_Admin_Model_User $admin): string
    {
        $now = new DateTimeImmutable();
        $config = $this->getConfig();

        $token = $config->builder()
            ->issuedBy($this->getIssuer())
            ->permittedFor(self::AUDIENCE)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('admin_' . $admin->getId())
            ->issuedAt($now)
            ->expiresAt($now->modify('+' . self::TOKEN_EXPIRY_SECONDS . ' seconds'))
            ->withClaim('admin_id', (int) $admin->getId())
            ->withClaim('email', $admin->getEmail())
            ->withClaim('type', 'admin')
            ->withClaim('roles', ['ROLE_ADMIN'])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
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
        $now = new DateTimeImmutable();
        $config = $this->getConfig();

        $token = $config->builder()
            ->issuedBy($this->getIssuer())
            ->permittedFor(self::AUDIENCE)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('api_user_' . $apiUser->getId())
            ->issuedAt($now)
            ->expiresAt($now->modify('+' . $this->getTokenExpiry() . ' seconds'))
            ->withClaim('api_user_id', (int) $apiUser->getId())
            ->withClaim('username', $apiUser->getUsername())
            ->withClaim('type', 'api_user')
            ->withClaim('roles', ['ROLE_API_USER'])
            ->withClaim('permissions', $permissions)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
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
     * @return object The decoded payload as stdClass
     * @throws \Lcobucci\JWT\Validation\RequiredConstraintsViolated If validation fails
     * @throws \Lcobucci\JWT\Token\InvalidTokenStructure If token format is invalid
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function decodeToken(string $token): object
    {
        $config = $this->getConfig();
        $parsed = $config->parser()->parse($token);

        $constraints = [
            new IssuedBy($this->getIssuer()),
            new PermittedFor(self::AUDIENCE),
            new StrictValidAt(SystemClock::fromUTC()),
        ];

        $config->validator()->assert($parsed, ...$constraints);

        // Convert to stdClass for backward compatibility
        $claims = $parsed->claims();
        $payload = new \stdClass();
        $payload->iss = $claims->get('iss');
        $payload->aud = $claims->get('aud');
        $payload->jti = $claims->get('jti');
        $payload->sub = $claims->get('sub');
        $payload->iat = $claims->get('iat');
        $payload->exp = $claims->get('exp');

        foreach (['customer_id', 'admin_id', 'api_user_id', 'email', 'username', 'type', 'roles', 'permissions', 'allowed_store_ids'] as $claim) {
            if ($claims->has($claim)) {
                $payload->$claim = $claims->get($claim);
            }
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

        // Fall back to deriving a secret from the Maho crypt key
        if (empty($secret)) {
            $cryptKey = (string) \Mage::getConfig()->getNode('global/crypt/key');
            if (!empty($cryptKey)) {
                $secret = hash('sha256', $cryptKey . ':maho_api_jwt');
            }
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
