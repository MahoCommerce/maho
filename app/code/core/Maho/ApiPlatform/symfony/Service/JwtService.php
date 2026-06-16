<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

/**
 * Centralized JWT token management.
 *
 * Consolidates JWT generation and validation logic from AuthController
 * and OAuth2Authenticator to ensure consistent behavior across the API.
 */
class JwtService
{
    private const CONFIG_PATH_SECRET = 'apiplatform/oauth2/secret';
    private const CONFIG_PATH_TOKEN_LIFETIME = 'apiplatform/oauth2/token_lifetime';
    private const DEFAULT_TOKEN_EXPIRY_SECONDS = 86400; // 24 hours
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
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+' . $this->getTokenExpiry() . ' seconds'))
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
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+' . $this->getTokenExpiry() . ' seconds'))
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
            ->canOnlyBeUsedAfter($now)
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
     * Load permissions for an API user from the api/rule table.
     *
     * The role editor (Maho_ApiPlatform_Adminhtml_Apiplatform_RoleController::saveAction)
     * stores `resource/operation` strings (e.g. `products/read`) directly in
     * `api/rule.resource_id`, validated against ApiPermissionRegistry. We read
     * those rows here so the permissions emitted match the format ApiUserVoter
     * checks (`resource/operation`, with `all` and `resource/all` shortcuts).
     *
     * The legacy `Mage::getSingleton('api/config')->getResources()` XML tree is
     * a different (resource-only) namespace and never matched the voter's
     * checks, using it here would mean api_user grants authorize nothing.
     *
     * @return array<string> e.g. ['orders/read', 'shipments/write', 'all']
     */
    public function loadApiUserPermissions(\Mage_Api_Model_User $apiUser): array
    {
        $roleIds = $apiUser->getRoles();
        if (empty($roleIds)) {
            return [];
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $ruleTable = $resource->getTableName('api/rule');

        $rows = $read->fetchCol(
            $read->select()
                ->from($ruleTable, ['resource_id'])
                ->where('role_id IN (?)', $roleIds)
                ->where('role_type = ?', 'G')
                ->where('api_permission = ?', 'allow'),
        );

        if (in_array('all', $rows, true)) {
            return ['all'];
        }

        return array_values(array_unique(array_filter(
            $rows,
            static fn(string $r): bool => $r !== '',
        )));
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
            // SignedWith verifies the HMAC signature; without it parse() only
            // decodes the token and any forged payload would be accepted.
            new SignedWith($config->signer(), $config->signingKey()),
            new IssuedBy($this->getIssuer()),
            new PermittedFor(self::AUDIENCE),
            new StrictValidAt(new class implements \Psr\Clock\ClockInterface {
                #[\Override]
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                }
            }),
        ];

        assert($parsed instanceof \Lcobucci\JWT\Token\Plain);
        $config->validator()->assert($parsed, ...$constraints);

        // Convert to stdClass for backward compatibility
        $claims = $parsed->claims();
        $payload = new \stdClass();
        $payload->iss = $claims->get('iss');
        $payload->aud = $claims->get('aud');
        $payload->jti = $claims->get('jti');
        $payload->sub = $claims->get('sub');
        // lcobucci hydrates registered date claims (iat/exp/nbf) as
        // DateTimeImmutable; expose them as unix timestamps so callers can treat
        // them as ints, casting the object to int throws \Error.
        $iat = $claims->get('iat');
        $exp = $claims->get('exp');
        $payload->iat = $iat instanceof \DateTimeInterface ? $iat->getTimestamp() : $iat;
        $payload->exp = $exp instanceof \DateTimeInterface ? $exp->getTimestamp() : $exp;

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
        } catch (\Exception) {
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
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get JWT secret from Maho configuration
     *
     * @return string The JWT secret
     * @throws \RuntimeException If secret is configured but too short
     */
    public function getSecret(): string
    {
        if ($this->cachedSecret !== null) {
            return $this->cachedSecret;
        }

        $secret = self::resolveSecret();

        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT secret must be at least 32 characters. Configure in System > Configuration > API > JWT.');
        }

        $this->cachedSecret = $secret;
        return $secret;
    }

    /**
     * Resolve the JWT signing secret, generating and persisting a strong
     * random one on first use. Single source of truth shared with the API
     * kernel's env-var resolution, so the admin/token path self-heals instead
     * of relying on the kernel having booted first (saveConfig only writes the
     * DB, not the in-memory config tree, so a stale read can't be avoided by
     * generating elsewhere).
     */
    public static function resolveSecret(): string
    {
        $secret = (string) \Mage::getStoreConfig(self::CONFIG_PATH_SECRET);

        // First boot: generate and persist a strong random secret rather than
        // deriving one from the encryption key (which would compound local.xml
        // exposure into a JWT-forgery primitive). No fallback to the crypt key.
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            \Mage::getConfig()->saveConfig(self::CONFIG_PATH_SECRET, $secret);
            \Mage::app()->getCache()->cleanType('config');
        }

        return $secret;
    }

    /**
     * Get token expiry in seconds
     */
    public function getTokenExpiry(): int
    {
        $configured = (int) \Mage::getStoreConfig(self::CONFIG_PATH_TOKEN_LIFETIME);
        return $configured > 0 ? $configured : self::DEFAULT_TOKEN_EXPIRY_SECONDS;
    }

    /**
     * Get the issuer URL for tokens.
     *
     * Prefer the secure base URL, issuer is a public claim and tokens are
     * meant to be served over HTTPS in production. Fall back to the unsecure
     * URL only when secure isn't configured (dev installs without TLS).
     */
    public function getIssuer(): string
    {
        // Pin issuer to the default-store base URL so issuance and verification
        // produce the same iss regardless of which store the verifying request
        // resolves to in multi-store installs (fix a16e02812).
        $storeId = \Maho\ApiPlatform\Service\StoreContext::getDefaultStoreId();
        $base = (string) \Mage::getStoreConfig('web/secure/base_url', $storeId);
        if ($base === '') {
            $base = (string) \Mage::getStoreConfig('web/unsecure/base_url', $storeId);
        }
        return rtrim($base, '/') . '/';
    }
}
