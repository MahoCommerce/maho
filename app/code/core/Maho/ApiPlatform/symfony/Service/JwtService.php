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
    // Matches Helper_Data::DEFAULT_TOKEN_LIFETIME and the shipped config default
    // (apiplatform/oauth2/token_lifetime). A blank config must not silently issue
    // longer-lived tokens than the documented 1h default.
    private const DEFAULT_TOKEN_EXPIRY_SECONDS = 3600; // 1 hour

    private ?string $cachedSecret = null;
    private ?Configuration $config = null;

    /**
     * Every audience a token may carry: the canonical resource identifiers this
     * install serves, per RFC 8707. Whether the specific audience covers the
     * resource being requested is a separate check, made per request by
     * OAuth2Authenticator.
     *
     * @return non-empty-list<non-empty-string>
     */
    public function getPermittedAudiences(): array
    {
        return $this->helper()->getCanonicalResources();
    }

    /**
     * The audience for a token that reaches the whole API surface, which is what
     * the three grants on /auth/token issue.
     */
    private function getApiAudience(): string
    {
        return rtrim($this->helper()->getRootUrl(), '/');
    }

    private function helper(): \Maho_ApiPlatform_Helper_Data
    {
        /** @var \Maho_ApiPlatform_Helper_Data $helper */
        $helper = \Mage::helper('apiplatform');
        return $helper;
    }

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
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $config = $this->getConfig();

        $token = $config->builder()
            ->issuedBy($this->getIssuer())
            ->permittedFor($this->getApiAudience())
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('customer_' . $customer->getId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+' . $this->getTokenExpiry() . ' seconds'))
            ->withClaim('customer_id', (int) $customer->getId())
            ->withClaim('email', $customer->getEmail())
            ->withClaim('type', 'customer')
            ->withClaim('roles', ['ROLE_CUSTOMER'])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    /**
     * Generate JWT token for an admin user
     *
     * @param string|null $audience Canonical resource URI the token is bound to,
     *                              per RFC 8707. Null keeps the legacy audience.
     * @param string|null $scope    OAuth scope granted, when the token came from
     *                              the authorization code flow.
     * @return string The JWT token
     * @throws \RuntimeException If JWT secret is not configured
     */
    public function generateAdminToken(
        \Mage_Admin_Model_User $admin,
        ?string $audience = null,
        ?string $scope = null,
    ): string {
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $config = $this->getConfig();

        $builder = $config->builder()
            ->issuedBy($this->getIssuer())
            ->permittedFor($audience ?? $this->getApiAudience())
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('admin_' . $admin->getId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+' . $this->getTokenExpiry() . ' seconds'))
            ->withClaim('admin_id', (int) $admin->getId())
            ->withClaim('email', $admin->getEmail())
            ->withClaim('type', 'admin')
            ->withClaim('roles', ['ROLE_ADMIN']);

        if ($scope !== null) {
            $builder = $builder->withClaim('scope', $scope);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
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
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $config = $this->getConfig();

        $builder = $config->builder()
            ->issuedBy($this->getIssuer())
            ->permittedFor($this->getApiAudience())
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('api_user_' . $apiUser->getId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+' . $this->getTokenExpiry() . ' seconds'))
            ->withClaim('api_user_id', (int) $apiUser->getId())
            ->withClaim('username', $apiUser->getUsername())
            ->withClaim('type', 'api_user')
            // No role claim: service accounts are authorized by their granular
            // `resource/op` permissions, not by a role (see OAuth2Authenticator).
            ->withClaim('roles', [])
            ->withClaim('permissions', $permissions);

        // Scope the token to the api user's allowed stores, when set. A
        // null/empty/invalid column means "all stores" — emit no claim, so the
        // consumer side (OAuth2Authenticator) leaves allowedStoreIds null.
        $allowedStoreIds = $this->getApiUserAllowedStoreIds($apiUser);
        if ($allowedStoreIds !== []) {
            $builder = $builder->withClaim('allowed_store_ids', array_map(intval(...), $allowedStoreIds));
        }

        $token = $builder->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    /**
     * Read and decode the api_user.allowed_store_ids JSON column.
     *
     * Returns the list of store ids the user is restricted to, or an empty
     * array when unrestricted (null/empty column or undecodable JSON — treated
     * conservatively as no restriction so a malformed value can't lock a user
     * out of every store).
     *
     * @return array<int, mixed>
     */
    public function getApiUserAllowedStoreIds(\Mage_Api_Model_User $apiUser): array
    {
        $raw = $apiUser->getData('allowed_store_ids');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = \Mage::helper('core')->jsonDecode($raw);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
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

        // resource_id is nullable in api/rule, so fetchCol() may return null
        // entries. Filter to non-empty strings (a bare `static fn(string $r)`
        // would TypeError on null under strict_types).
        return array_values(array_unique(array_filter(
            $rows,
            static fn(mixed $r): bool => is_string($r) && $r !== '',
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
    public function decodeToken(#[\SensitiveParameter]
        string $token): object
    {
        $config = $this->getConfig();
        $parsed = $config->parser()->parse($token);

        $constraints = [
            // SignedWith verifies the HMAC signature; without it parse() only
            // decodes the token and any forged payload would be accepted.
            new SignedWith($config->signer(), $config->signingKey()),
            new IssuedBy($this->getIssuer()),
            new \Maho\ApiPlatform\Validation\PermittedForAny($this->getPermittedAudiences()),
            new StrictValidAt(new \Maho\UtcClock()),
        ];

        // Hard guard rather than assert(): assertions may be disabled in
        // production (assert.active=0), and a non-Plain (e.g. Unsecured) token
        // must never bypass the SignedWith constraint below.
        if (!$parsed instanceof \Lcobucci\JWT\Token\Plain) {
            throw new \Lcobucci\JWT\Token\InvalidTokenStructure('Token is not a valid signed JWT.');
        }
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

        foreach (['customer_id', 'admin_id', 'api_user_id', 'email', 'username', 'type', 'roles', 'permissions', 'allowed_store_ids', 'scope'] as $claim) {
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
    public function isValidToken(#[\SensitiveParameter]
        string $token): bool
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
    public function getCustomerIdFromToken(#[\SensitiveParameter]
        string $token): ?int
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
    public function getAdminIdFromToken(#[\SensitiveParameter]
        string $token): ?int
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
        // Stored encrypted at rest (the config field declares the encrypted
        // backend model); decrypt on read so a DB-only leak can't hand over a
        // usable signing key.
        $stored = (string) \Mage::getStoreConfig(self::CONFIG_PATH_SECRET);
        if ($stored !== '') {
            return (string) \Mage::helper('core')->decrypt($stored);
        }

        // saveConfig() writes the DB but not the in-memory config tree, so the
        // read above still returns empty for the rest of this request even
        // after a secret was persisted a moment ago. Go to the DB before
        // generating, or a second caller in the same process mints a new secret
        // and invalidates every token the first one just signed.
        $committed = self::readCommittedSecret();
        if ($committed !== '') {
            return (string) \Mage::helper('core')->decrypt($committed);
        }

        // First boot: generate and persist a strong random secret rather than
        // deriving one from the encryption key. No fallback to the crypt key.
        $secret = bin2hex(random_bytes(32));
        \Mage::getConfig()->saveConfig(self::CONFIG_PATH_SECRET, \Mage::helper('core')->encrypt($secret));
        \Mage::app()->getCache()->cleanType('config');

        // First-boot race: two concurrent workers can each generate a secret and
        // the last saveConfig() wins in the DB. Re-read the committed (encrypted)
        // value so every worker converges on the persisted secret.
        $committed = self::readCommittedSecret();
        if ($committed !== '') {
            $secret = (string) \Mage::helper('core')->decrypt($committed);
        }

        return $secret;
    }

    /**
     * The encrypted secret as it stands in the database, bypassing both the
     * config cache and the in-memory config tree.
     */
    private static function readCommittedSecret(): string
    {
        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');

        return (string) $read->fetchOne(
            $read->select()
                ->from($resource->getTableName('core/config_data'), ['value'])
                ->where('path = ?', self::CONFIG_PATH_SECRET)
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0),
        );
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
     *
     * No trailing slash: a client compares `iss` against the `issuer` of the
     * RFC 8414 document character by character, and that one is the bare root.
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
        return rtrim($base, '/');
    }
}
