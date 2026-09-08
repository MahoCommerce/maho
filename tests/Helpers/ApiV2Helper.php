<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maho\ApiPlatform\Service\JwtService;

/**
 * API v2 Test Helper.
 *
 * Provides HTTP client methods, JWT token generation, and test fixtures
 * for integration testing the API Platform REST and GraphQL endpoints.
 */
class ApiV2Helper
{
    /** MCP spec revision the test client negotiates. */
    public const MCP_PROTOCOL_VERSION = '2025-06-18';

    private static ?string $baseUrl = null;
    private static ?string $jwtSecret = null;
    private static ?Configuration $jwtConfig = null;
    private static ?JwtService $jwtService = null;

    /** @var array<string, list<int>> Entity IDs created during tests, keyed by type */
    private static array $createdEntities = [];

    /**
     * Track a created entity for cleanup
     */
    public static function trackCreated(string $type, int $id): void
    {
        self::$createdEntities[$type][] = $id;
    }

    /**
     * Clean up all tracked entities via direct DB
     *
     * Call this in afterAll() hooks to remove test data.
     */
    public static function cleanup(): void
    {
        try {
            $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        } catch (\Exception $e) {
            return; // DB not available
        }

        // Delete quotes (carts) and related records
        if (!empty(self::$createdEntities['quote'])) {
            $ids = self::$createdEntities['quote'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM sales_flat_quote_item WHERE quote_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_quote_address WHERE quote_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_quote_payment WHERE quote_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_quote WHERE entity_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete reviews
        if (!empty(self::$createdEntities['review'])) {
            $ids = self::$createdEntities['review'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM review_detail WHERE review_id IN ({$idList})");
                $write->query("DELETE FROM review_entity_summary WHERE entity_pk_value IN (SELECT entity_pk_value FROM review WHERE review_id IN ({$idList}))");
                $write->query("DELETE FROM review_store WHERE review_id IN ({$idList})");
                $write->query("DELETE FROM review WHERE review_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete wishlist items
        if (!empty(self::$createdEntities['wishlist_item'])) {
            $ids = self::$createdEntities['wishlist_item'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM wishlist_item WHERE wishlist_item_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete CMS pages
        if (!empty(self::$createdEntities['cms_page'])) {
            $ids = self::$createdEntities['cms_page'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM cms_page_store WHERE page_id IN ({$idList})");
                $write->query("DELETE FROM cms_page WHERE page_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete CMS blocks
        if (!empty(self::$createdEntities['cms_block'])) {
            $ids = self::$createdEntities['cms_block'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM cms_block_store WHERE block_id IN ({$idList})");
                $write->query("DELETE FROM cms_block WHERE block_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete blog posts
        if (!empty(self::$createdEntities['blog_post'])) {
            $ids = self::$createdEntities['blog_post'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM blog_post_store WHERE post_id IN ({$idList})");
                $write->query("DELETE FROM blog_post WHERE post_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete categories
        if (!empty(self::$createdEntities['category'])) {
            $ids = self::$createdEntities['category'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM catalog_category_entity WHERE entity_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete revocation requests
        if (!empty(self::$createdEntities['revocation_request'])) {
            $ids = self::$createdEntities['revocation_request'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM revocation_request WHERE request_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete orders and related records
        if (!empty(self::$createdEntities['order'])) {
            $ids = self::$createdEntities['order'];
            $idList = implode(',', array_map('intval', $ids));
            try {
                $write->query("DELETE FROM sales_flat_order_item WHERE order_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_order_address WHERE parent_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_order_payment WHERE parent_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_order_status_history WHERE parent_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_order_grid WHERE entity_id IN ({$idList})");
                $write->query("DELETE FROM sales_flat_order WHERE entity_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete products (EAV entity, must use model delete for proper cleanup)
        if (!empty(self::$createdEntities['product'])) {
            $ids = self::$createdEntities['product'];
            try {
                $appEmulation = \Mage::getSingleton('core/app_emulation');
                $initialEnv = $appEmulation->startEnvironmentEmulation(0, 'admin');
                foreach ($ids as $id) {
                    try {
                        $product = \Mage::getModel('catalog/product')->load((int) $id);
                        if ($product->getId()) {
                            $product->delete();
                        }
                    } catch (\Exception $e) {
                        $write->query('DELETE FROM catalog_product_entity WHERE entity_id = ' . (int) $id);
                    }
                }
                $appEmulation->stopEnvironmentEmulation($initialEnv);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete admin users (and their role-assignment rows in admin_role)
        if (!empty(self::$createdEntities['admin_user'])) {
            $idList = implode(',', array_map('intval', self::$createdEntities['admin_user']));
            try {
                $write->query("DELETE FROM admin_role WHERE user_id IN ({$idList})");
                $write->query("DELETE FROM admin_user WHERE user_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete admin roles (group rows) and any ACL rules attached to them
        if (!empty(self::$createdEntities['admin_role'])) {
            $idList = implode(',', array_map('intval', self::$createdEntities['admin_role']));
            try {
                $write->query("DELETE FROM admin_rule WHERE role_id IN ({$idList})");
                $write->query("DELETE FROM admin_role WHERE role_id IN ({$idList})");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Delete simple config-table rows created by CRUD tests (safety net for
        // a test that fails before its own DELETE call). Each maps a tracked
        // type to its table + primary key.
        $simpleTables = [
            'customer_group' => ['customer_group', 'customer_group_id'],
            'tax_class' => ['tax_class', 'class_id'],
            'tax_rate' => ['tax_calculation_rate', 'tax_calculation_rate_id'],
            'tax_rule' => ['tax_calculation_rule', 'tax_calculation_rule_id'],
        ];
        foreach ($simpleTables as $type => [$table, $pk]) {
            if (!empty(self::$createdEntities[$type])) {
                $idList = implode(',', array_map('intval', self::$createdEntities[$type]));
                try {
                    $write->query("DELETE FROM {$table} WHERE {$pk} IN ({$idList})");
                } catch (\Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }
        self::$createdEntities = [];
    }

    /**
     * Get count of tracked entities (for debugging)
     *
     * @return array<string, int>
     */
    public static function getTrackedCounts(): array
    {
        return array_map('count', self::$createdEntities);
    }

    /**
     * HTTP GET request
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function get(string $path, ?string $token = null, array $extraHeaders = []): array
    {
        return self::request('GET', $path, null, $token, $extraHeaders);
    }

    /**
     * HTTP GET for binary/non-JSON bodies (e.g. PDF downloads).
     *
     * Skips JSON decoding and normalizes the raw header lines into a
     * lowercased name => list-of-values map so callers can assert on
     * headers like content-type without re-parsing.
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, raw: string, headers: array<string, list<string>>}
     */
    public static function getRaw(string $path, ?string $token = null, array $extraHeaders = []): array
    {
        $response = self::request('GET', $path, null, $token, $extraHeaders);

        $headers = [];
        foreach ($response['headers'] as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue; // status line or malformed header
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $headers[$name][] = trim(substr($line, $pos + 1));
        }

        return [
            'status' => $response['status'],
            'raw' => $response['raw'],
            'headers' => $headers,
        ];
    }

    /**
     * HTTP POST request
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function post(string $path, array $data, ?string $token = null, array $extraHeaders = []): array
    {
        return self::request('POST', $path, $data, $token, $extraHeaders);
    }

    /**
     * HTTP POST with a raw, unencoded body — for sending deliberately malformed
     * JSON or other non-array payloads that post() would json_encode.
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function postRaw(string $path, string $body, ?string $token = null, array $extraHeaders = []): array
    {
        return self::request('POST', $path, $body, $token, $extraHeaders);
    }

    /**
     * Send one JSON-RPC message to the MCP endpoint.
     *
     * The streamable HTTP transport answers `initialize` with an `Mcp-Session-Id`
     * header that every later message must echo back, and it may reply as
     * server-sent events rather than plain JSON, so the payload is unwrapped here.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array, session: ?string}
     */
    public static function mcp(array $payload, ?string $token = null, ?string $sessionId = null, array $extraHeaders = []): array
    {
        $headers = $extraHeaders + [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => self::MCP_PROTOCOL_VERSION,
        ];
        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        $response = self::postRaw('/api/mcp', (string) json_encode($payload), $token, $headers);

        $body = $response['raw'];
        if (preg_match_all('/^data: (.*)$/m', $body, $matches) === 1) {
            $body = (string) end($matches[1]);
        }
        $response['json'] = json_decode(trim($body), true) ?? [];

        $response['session'] = $sessionId;
        foreach ($response['headers'] as $header) {
            if (stripos($header, 'mcp-session-id:') === 0) {
                $response['session'] = trim(substr($header, strlen('mcp-session-id:')));
            }
        }

        return $response;
    }

    /**
     * Perform the MCP handshake and return the session id, or null when the
     * endpoint refused (protocol disabled, transport error).
     */
    public static function mcpSession(?string $token = null): ?string
    {
        return self::mcp([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::MCP_PROTOCOL_VERSION,
                'capabilities' => [],
                'clientInfo' => ['name' => 'maho-pest', 'version' => '1.0'],
            ],
        ], $token)['session'];
    }

    /**
     * Call one MCP tool on an existing session.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array, session: ?string}
     */
    public static function mcpTool(string $name, array $arguments, ?string $token, ?string $sessionId, array $extraHeaders = []): array
    {
        return self::mcp([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ], $token, $sessionId, $extraHeaders);
    }

    /**
     * Every tool name the caller can see, following pagination cursors.
     *
     * @return array<string, array<string, mixed>> keyed by tool name
     */
    public static function mcpTools(?string $token, ?string $sessionId): array
    {
        $tools = [];
        $cursor = null;
        do {
            $response = self::mcp([
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'tools/list',
                'params' => $cursor === null ? [] : ['cursor' => $cursor],
            ], $token, $sessionId);

            foreach ($response['json']['result']['tools'] ?? [] as $tool) {
                $tools[$tool['name']] = $tool;
            }
            $cursor = $response['json']['result']['nextCursor'] ?? null;
        } while ($cursor !== null);

        return $tools;
    }

    /**
     * HTTP PUT request
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function put(string $path, array $data, ?string $token = null, array $extraHeaders = []): array
    {
        return self::request('PUT', $path, $data, $token, $extraHeaders);
    }

    /**
     * HTTP DELETE request
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function delete(string $path, ?string $token = null, array $extraHeaders = []): array
    {
        return self::request('DELETE', $path, null, $token, $extraHeaders);
    }

    /**
     * HTTP OPTIONS request, primarily for CORS preflight.
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function options(string $path, array $extraHeaders = []): array
    {
        return self::request('OPTIONS', $path, null, null, $extraHeaders);
    }

    /**
     * Find a single response header value (case-insensitive). Returns null if absent.
     *
     * @param array{status: int, json: array, raw: string, headers: array} $response
     */
    public static function headerValue(array $response, string $name): ?string
    {
        // A header may appear on multiple lines (e.g. several `Vary:` entries from
        // CORS + the cache listener). HTTP treats repeated headers as a single
        // comma-joined value, so aggregate every matching line rather than
        // returning only the first.
        $needle = strtolower($name) . ':';
        $values = [];
        foreach ($response['headers'] as $line) {
            if (stripos($line, $needle) === 0) {
                $values[] = trim(substr($line, strlen($needle)));
            }
        }
        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * GraphQL request
     *
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function graphql(string $query, array $variables = [], ?string $token = null): array
    {
        $data = ['query' => $query];
        if (!empty($variables)) {
            $data['variables'] = $variables;
        }
        return self::request('POST', '/api/graphql', $data, $token);
    }

    // ── JWT Token Generation (lcobucci/jwt) ──────────────────────────

    private static function getJwtConfig(): Configuration
    {
        if (self::$jwtConfig === null) {
            self::$jwtConfig = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText(self::getJwtSecret()),
            );
        }
        return self::$jwtConfig;
    }

    /**
     * Build a JWT token from a claims array using lcobucci/jwt.
     */
    private static function buildToken(array $claims, ?string $secret = null): string
    {
        if ($secret !== null) {
            // Custom secret (e.g. for invalid token tests)
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($secret),
            );
        } else {
            $config = self::getJwtConfig();
        }

        $now = new \DateTimeImmutable();
        $builder = $config->builder()
            ->issuedBy($claims['iss'] ?? self::jwtService()->getIssuer())
            ->permittedFor($claims['aud'] ?? self::jwtService()->getApiAudience())
            ->identifiedBy($claims['jti'] ?? bin2hex(random_bytes(16)))
            ->issuedAt($now)
            // The server validates with StrictValidAt, which REQUIRES the nbf
            // claim; JwtService sets it via canOnlyBeUsedAfter(). Without it every
            // token is rejected as '"Not Before" claim missing'.
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+' . (($claims['exp'] ?? time() + 86400) - time()) . ' seconds'));

        if (isset($claims['sub'])) {
            $builder = $builder->relatedTo($claims['sub']);
        }

        // Add custom claims (skip standard JWT fields handled above)
        $standardFields = ['iss', 'aud', 'jti', 'iat', 'exp', 'sub'];
        foreach ($claims as $key => $value) {
            if (!in_array($key, $standardFields, true)) {
                $builder = $builder->withClaim($key, $value);
            }
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /**
     * Generate a valid customer JWT token
     */
    public static function generateCustomerToken(?int $customerId = null): string
    {
        $customerId ??= self::fixtures('customer_id');

        return self::buildToken([
            'sub' => 'customer_' . $customerId,
            'customer_id' => $customerId,
            'email' => self::fixtures('customer_email'),
            'type' => 'customer',
            'roles' => ['ROLE_CUSTOMER'],
        ]);
    }

    /**
     * Generate a valid admin JWT token
     */
    public static function generateAdminToken(): string
    {
        return self::buildToken([
            'sub' => 'admin_1',
            'admin_id' => 1,
            'email' => 'admin@example.com',
            'type' => 'admin',
            'roles' => ['ROLE_ADMIN'],
        ]);
    }

    /**
     * Generate an expired JWT token
     */
    public static function generateExpiredToken(): string
    {
        $config = self::getJwtConfig();
        $past = new \DateTimeImmutable('-2 days');

        $token = $config->builder()
            ->issuedBy(self::jwtService()->getIssuer())
            ->permittedFor(self::jwtService()->getApiAudience())
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('customer_1')
            ->issuedAt($past)
            ->expiresAt($past->modify('+1 day')) // expired 1 day ago
            ->withClaim('customer_id', 1)
            ->withClaim('type', 'customer')
            ->withClaim('roles', ['ROLE_CUSTOMER'])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    /**
     * Generate a JWT token signed with a wrong key
     */
    public static function generateInvalidToken(): string
    {
        return self::buildToken([
            'sub' => 'customer_1',
            'customer_id' => 1,
            'type' => 'customer',
            'roles' => ['ROLE_CUSTOMER'],
        ], 'wrong-secret-key-that-does-not-match');
    }

    /**
     * Generate a custom JWT token with arbitrary payload
     */
    public static function generateToken(array $payload): string
    {
        // Remove null values
        $payload = array_filter($payload, fn($v) => $v !== null);

        // Check if a custom secret is needed
        $secret = null;
        if (isset($payload['_secret'])) {
            $secret = $payload['_secret'];
            unset($payload['_secret']);
        }

        return self::buildToken($payload, $secret);
    }

    /** @var array<string, int> Cache of API user ids keyed by permission signature. */
    private static array $apiUserCache = [];

    /**
     * Build a service (api_user) token backed by a REAL API user. The server
     * re-reads permissions and store scope from the DB (it ignores JWT-embedded
     * permission claims and requires a valid api_user_id), so the token must
     * reference an actual api/user row with the requested `resource/op` rules.
     *
     * @param array<int, string> $permissions e.g. ['products/write'] or ['all']
     * @param array<int, int>|null $storeIds   null = unrestricted
     */
    public static function generateServiceToken(array $permissions, ?array $storeIds = null, string $identity = 'api_user_test'): string
    {
        self::ensureMahoBootstrapped();
        $userId = self::ensureApiUser($permissions, $storeIds);

        return self::generateToken([
            'sub' => $identity,
            'type' => 'api_user',
            'api_user_id' => $userId,
            'allowed_store_ids' => $storeIds,
        ]);
    }

    /**
     * Create (or reuse) an API user with a group role granting $permissions,
     * mirroring the writes the admin Role/User controllers perform.
     *
     * @param array<int, string> $permissions
     * @param array<int, int>|null $storeIds
     */
    private static function ensureApiUser(array $permissions, ?array $storeIds): int
    {
        sort($permissions);
        $key = md5((string) json_encode([$permissions, $storeIds]));
        if (isset(self::$apiUserCache[$key])) {
            return self::$apiUserCache[$key];
        }

        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $roleTable = $resource->getTableName('api/role');
        $ruleTable = $resource->getTableName('api/rule');
        $suffix = substr($key, 0, 8);

        // Group role.
        $write->insert($roleTable, [
            'parent_id' => 0,
            'tree_level' => 1,
            'sort_order' => 0,
            'role_type' => 'G',
            'user_id' => 0,
            'role_name' => 'apitest-role-' . $suffix,
        ]);
        $roleId = (int) $write->lastInsertId();

        // Permission rules (resource_id = 'all' or 'resource/op' strings).
        foreach ($permissions as $permission) {
            if ($permission === '') {
                continue;
            }
            $write->insert($ruleTable, [
                'role_id' => $roleId,
                'resource_id' => $permission,
                'api_privileges' => null,
                'assert_id' => 0,
                'role_type' => 'G',
                'api_permission' => 'allow',
            ]);
        }

        // API user.
        $user = \Mage::getModel('api/user');
        $user->setUsername('apitest_' . $suffix)
            ->setFirstname('API')
            ->setLastname('Service')
            ->setEmail('apitest_' . $suffix . '@example.com')
            ->setApiKey('ApiTest' . $suffix . 'Secret123')
            ->setIsActive(1);
        if ($storeIds !== null) {
            $user->setData('allowed_store_ids', (string) json_encode($storeIds));
        }
        $user->save();
        $userId = (int) $user->getId();

        // Link user → role (role_type 'U'); Mage_Api_Model_User::getRoles() reads this.
        $write->insert($roleTable, [
            'parent_id' => $roleId,
            'tree_level' => 2,
            'sort_order' => 0,
            'role_type' => 'U',
            'user_id' => $userId,
            'role_name' => $user->getUsername(),
        ]);

        self::$apiUserCache[$key] = $userId;
        return $userId;
    }

    /**
     * HTTP POST multipart/form-data request (for file uploads)
     *
     * @param array<string, string> $fields Form fields
     * @param array<string, string> $files File fields (name => filepath)
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function postMultipart(string $path, array $fields, array $files, ?string $token = null): array
    {
        $url = self::getBaseUrl() . $path;
        $boundary = 'boundary' . uniqid();

        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        foreach ($files as $name => $filepath) {
            $filename = basename($filepath);
            $mime = mime_content_type($filepath) ?: 'application/octet-stream';
            $content = file_get_contents($filepath);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: {$mime}\r\n\r\n";
            $body .= "{$content}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $headers = [
            "Content-Type: multipart/form-data; boundary={$boundary}",
            'Accept: application/json',
        ];

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        } else {
            $headers[] = 'Authorization: Basic ' . base64_encode(getenv('API_TEST_BASIC_AUTH') ?: 'user:pass');
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 30,
                'follow_location' => false,
            ],
        ];

        $requestUrl = $url;
        if ($token !== null) {
            $parsed = parse_url($url);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? 'localhost';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $pathPart = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
            $requestUrl = "{$scheme}://" . (getenv('API_TEST_BASIC_AUTH') ?: 'user:pass') . "@{$host}{$port}{$pathPart}";
        }

        $context = stream_context_create($options);
        $raw = @file_get_contents($requestUrl, false, $context);

        $status = 500;
        $responseHeaders = $http_response_header ?? [];
        if (!empty($responseHeaders)) {
            if (preg_match('/HTTP\/\d+\.?\d*\s+(\d+)/', $responseHeaders[0], $matches)) {
                $status = (int) $matches[1];
            }
        }

        if ($raw === false) {
            return [
                'status' => $status,
                'json' => ['error' => 'connection_failed', 'message' => 'Failed to connect to API'],
                'raw' => '',
                'headers' => $responseHeaders,
            ];
        }

        return [
            'status' => $status,
            'json' => json_decode($raw, true) ?? [],
            'raw' => $raw,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * Ensure Maho is bootstrapped for DB lookups and config access
     */
    public static function ensureMahoBootstrapped(): void
    {
        static $bootstrapped = false;
        if (!$bootstrapped) {
            try {
                \Mage::app();
                $bootstrapped = true;
            } catch (\Throwable $e) {
                // Unable to bootstrap, DB lookups will use fallbacks
            }
        }
    }

    /**
     * Get a test fixture value
     */
    public static function fixtures(string $key): mixed
    {
        // Resolved on demand (needs a live API round-trip) so it doesn't slow
        // every unrelated test that touches fixtures().
        if ($key === 'existing_cart_id') {
            return self::ensureExistingGuestCart();
        }

        static $fixtures = null;

        if ($fixtures === null) {
            self::ensureMahoBootstrapped();

            $productData = self::lookupProduct();
            $configurableSku = self::lookupConfigurableSku();
            $categoryId = self::lookupCategoryId();
            $customerId = self::lookupCustomerId();

            // Sample data ships no sales records, so seed a small, self-consistent
            // set (orders, invoices, a shipment, a gift card, a review) once, so the
            // read/lifecycle tests have real data to act on instead of skipping.
            $seed = self::seedSalesData($customerId, $productData['sku']);

            // Invoice fixtures: an invoice owned by the test customer enables the
            // customer PDF path; one owned by a *different* account (or a guest
            // order) drives the cross-tenant deny path. Any may be null on a DB
            // without matching data, in which case the dependent tests skip.
            $ownInvoice = self::lookupCustomerOwnedInvoice($customerId);
            $foreignInvoice = self::lookupForeignInvoice($customerId);

            $fixtures = [
                'customer_id' => $customerId,
                'customer_email' => self::lookupCustomerEmail($customerId),
                'second_customer_id' => self::lookupSecondCustomerId(),
                'invalid_customer_id' => 999999,
                'product_id' => $productData['id'],
                'product_sku' => $productData['sku'],
                'configurable_sku' => $configurableSku,
                'category_id' => $categoryId,
                'invalid_product_id' => 999999,
                'order_id' => $seed['order_id'] ?? self::lookupOrderId(),
                'invalid_order_id' => 999999,
                'invoice_id' => self::lookupInvoiceId(),
                'customer_order_id' => $ownInvoice['orderId'] ?? null,
                'customer_invoice_id' => $ownInvoice['invoiceId'] ?? null,
                'other_customer_order_id' => $foreignInvoice['orderId'] ?? null,
                'other_customer_invoice_id' => $foreignInvoice['invoiceId'] ?? null,
                'write_test_sku' => $productData['sku'],
                'write_test_qty' => 1,
                'giftcard_code' => $seed['giftcard_code'],
                'giftcard_id' => $seed['giftcard_id'],
                'blog_post_url_key' => null,
            ];
        }

        return $fixtures[$key] ?? null;
    }

    /**
     * Create (once) a guest cart with a single item via the API and return its
     * masked id, for the guest-cart read tests that need a populated cart. The
     * quote is tracked for cleanup. Returns null if the cart can't be created.
     */
    private static function ensureExistingGuestCart(): ?string
    {
        static $resolved = false;
        static $maskedId = null;

        if ($resolved) {
            return $maskedId;
        }
        $resolved = true;

        try {
            $create = self::post('/api/rest/v2/guest-carts', []);
            if ($create['status'] !== 201 || empty($create['json']['maskedId'])) {
                return null;
            }
            if (!empty($create['json']['id'])) {
                self::trackCreated('quote', (int) $create['json']['id']);
            }
            $maskedId = (string) $create['json']['maskedId'];

            // Add an item so structure/totals assertions have data to inspect.
            $sku = self::fixtures('write_test_sku');
            if ($sku) {
                self::post("/api/rest/v2/guest-carts/{$maskedId}/items", ['sku' => $sku, 'qty' => 1]);
            }
        } catch (\Throwable $e) {
            $maskedId = null;
        }

        return $maskedId;
    }

    /**
     * Make an HTTP request to the API
     *
     * @param array<string, string> $extraHeaders
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    private static function request(string $method, string $path, array|string|null $data = null, ?string $token = null, array $extraHeaders = []): array
    {
        $url = self::getBaseUrl() . $path;

        $headers = [
            'Accept: application/ld+json, application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(getenv('API_TEST_BASIC_AUTH') ?: 'user:pass'),
        ];

        if ($token !== null) {
            $headers = array_filter($headers, fn($h) => !str_starts_with($h, 'Authorization:'));
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        foreach ($extraHeaders as $name => $value) {
            // If a caller explicitly overrides one of the defaults (Accept,
            // Content-Type, Authorization), drop the default rather than
            // sending two of the same header.
            $headers = array_filter($headers, fn($h) => stripos($h, $name . ':') !== 0);
            $headers[] = $name . ': ' . $value;
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 30,
                'follow_location' => false,
            ],
        ];

        if ($data !== null) {
            $options['http']['content'] = is_array($data) ? json_encode($data) : $data;
        }

        $requestUrl = $url;
        if ($token !== null) {
            $parsed = parse_url($url);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? 'localhost';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $pathAndQuery = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
            $requestUrl = "{$scheme}://" . (getenv('API_TEST_BASIC_AUTH') ?: 'user:pass') . "@{$host}{$port}{$pathAndQuery}";
        }

        $context = stream_context_create($options);
        $raw = @file_get_contents($requestUrl, false, $context);

        $status = 500;
        $responseHeaders = $http_response_header ?? [];
        if (!empty($responseHeaders)) {
            if (preg_match('/HTTP\/\d+\.?\d*\s+(\d+)/', $responseHeaders[0], $matches)) {
                $status = (int) $matches[1];
            }
        }

        if ($raw === false) {
            return [
                'status' => $status,
                'json' => ['error' => 'connection_failed', 'message' => 'Failed to connect to API'],
                'raw' => '',
                'headers' => $responseHeaders,
            ];
        }

        $json = json_decode($raw, true) ?? [];

        return [
            'status' => $status,
            'json' => $json,
            'raw' => $raw,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * Public accessor for getBaseUrl (used by Pest.php availability check)
     */
    public static function getBaseUrlPublic(): string
    {
        return self::getBaseUrl();
    }

    private static function getBaseUrl(): string
    {
        if (self::$baseUrl !== null) {
            return self::$baseUrl;
        }

        // Read via getenv() as well as $_ENV: PHP CLI's default variables_order
        // ("GPCS") does not populate $_ENV from the environment, so a workflow
        // that exports API_BASE_URL is only visible through getenv(). Without
        // this the whole Api/V2 suite silently self-skips in CI.
        $envBaseUrl = $_ENV['API_BASE_URL'] ?? '';
        if ($envBaseUrl === '') {
            $envBaseUrl = getenv('API_BASE_URL') ?: '';
        }
        if ($envBaseUrl !== '') {
            self::$baseUrl = rtrim($envBaseUrl, '/');
            return self::$baseUrl;
        }

        try {
            self::ensureMahoBootstrapped();
            $baseUrl = \Mage::getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);
            if ($baseUrl && filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                self::$baseUrl = rtrim($baseUrl, '/');
                return self::$baseUrl;
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }

        self::$baseUrl = getenv('API_TEST_BASE_URL') ?: 'https://localhost';
        return self::$baseUrl;
    }

    /**
     * The service that mints and validates tokens on the server. A forged test
     * token must carry the issuer and audience this service expects, so ask it
     * rather than rebuild the rule here: a rebuilt rule drifts.
     */
    private static function jwtService(): JwtService
    {
        if (self::$jwtService === null) {
            self::ensureMahoBootstrapped();
            self::$jwtService = new JwtService();
        }

        return self::$jwtService;
    }

    /**
     * Get JWT secret from Maho configuration
     */
    private static function getJwtSecret(): string
    {
        if (self::$jwtSecret !== null) {
            return self::$jwtSecret;
        }

        try {
            self::$jwtSecret = self::jwtService()->getSecret();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Cannot get JWT secret: ' . $e->getMessage());
        }

        return self::$jwtSecret;
    }

    private static function lookupCustomerId(): ?int
    {
        // Use a dedicated, known-good API test customer rather than whatever
        // the sample data ships (an existing account may carry a pending
        // confirmation key, which the API auth path rejects → every
        // customer-token test 401s). Load-or-create it, and force it active
        // with no confirmation so the server accepts its token.
        return self::loadOrCreateTestCustomer('api.tester@example.com', 'API', 'Tester') ?? 1;
    }

    /**
     * A second real, active customer used by cross-tenant ownership tests: the
     * "intruder" that must be denied access to another customer's cart/order.
     * Must be a genuine account (not just id+1) or the JWT auth layer rejects
     * its token with 401 before the ownership check ever runs.
     */
    private static function lookupSecondCustomerId(): ?int
    {
        return self::loadOrCreateTestCustomer('api.tester2@example.com', 'API', 'Intruder');
    }

    /** Load-or-create an active, confirmed customer by email; returns its id or null on failure. */
    private static function loadOrCreateTestCustomer(string $email, string $firstname, string $lastname): ?int
    {
        try {
            $websiteId = (int) \Mage::app()->getStore(1)->getWebsiteId() ?: 1;

            $customer = \Mage::getModel('customer/customer')
                ->setWebsiteId($websiteId)
                ->loadByEmail($email);

            if (!$customer->getId()) {
                $customer->setWebsiteId($websiteId)
                    ->setStoreId(1)
                    ->setFirstname($firstname)
                    ->setLastname($lastname)
                    ->setEmail($email)
                    ->setPassword('ApiTester12345!');
            }

            $customer->setIsActive(1)
                ->setForceConfirmed(true)
                ->setConfirmation(null)
                ->save();

            return (int) $customer->getId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{id: int|null, sku: string|null}
     */
    private static function lookupProduct(): array
    {
        try {
            $product = \Mage::getModel('catalog/product')->getCollection()
                ->addFieldToFilter('type_id', \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                ->addFieldToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setPageSize(1)
                ->getFirstItem();
            if ($product->getId()) {
                return ['id' => (int) $product->getId(), 'sku' => $product->getSku()];
            }
        } catch (\Throwable $e) {
        }
        return ['id' => null, 'sku' => null];
    }

    private static function lookupConfigurableSku(): ?string
    {
        try {
            $product = \Mage::getModel('catalog/product')->getCollection()
                ->addFieldToFilter('type_id', \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                ->addFieldToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setPageSize(1)
                ->getFirstItem();
            return $product->getId() ? $product->getSku() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function lookupCategoryId(): ?int
    {
        try {
            $rootId = (int) \Mage::app()->getStore(1)->getRootCategoryId();
            $category = \Mage::getModel('catalog/category')->getCollection()
                ->addFieldToFilter('path', ['like' => "1/{$rootId}/%"])
                ->addFieldToFilter('level', ['gt' => 1])
                ->addFieldToFilter('is_active', 1)
                ->setPageSize(1)
                ->getFirstItem();
            return $category->getId() ? (int) $category->getId() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function lookupOrderId(): ?int
    {
        try {
            $order = \Mage::getModel('sales/order')->getCollection()
                ->setOrder('entity_id', 'ASC')
                ->setPageSize(1)
                ->getFirstItem();
            return $order->getId() ? (int) $order->getId() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function lookupInvoiceId(): ?int
    {
        try {
            $read = \Mage::getSingleton('core/resource')->getConnection('core_read');
            $id = $read->fetchOne('SELECT entity_id FROM sales_flat_invoice ORDER BY entity_id ASC LIMIT 1');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * An invoice whose order is owned by the given customer (not a guest).
     *
     * @return array{orderId: int, invoiceId: int}|null
     */
    private static function lookupCustomerOwnedInvoice(?int $customerId): ?array
    {
        if (!$customerId) {
            return null;
        }
        return self::lookupInvoiceRow("o.customer_is_guest = 0 AND o.customer_id = {$customerId}");
    }

    /**
     * An invoice whose order does NOT belong to the given customer (a guest
     * order, or one owned by a different account). Drives cross-tenant deny.
     *
     * @return array{orderId: int, invoiceId: int}|null
     */
    private static function lookupForeignInvoice(?int $customerId): ?array
    {
        $customerId = (int) $customerId;
        return self::lookupInvoiceRow("o.customer_id IS NULL OR o.customer_id <> {$customerId}");
    }

    /**
     * @return array{orderId: int, invoiceId: int}|null
     */
    private static function lookupInvoiceRow(string $whereOrderCondition): ?array
    {
        try {
            $read = \Mage::getSingleton('core/resource')->getConnection('core_read');
            $row = $read->fetchRow(
                'SELECT i.entity_id AS invoice_id, i.order_id
                 FROM sales_flat_invoice i
                 JOIN sales_flat_order o ON o.entity_id = i.order_id
                 WHERE ' . $whereOrderCondition . '
                 ORDER BY i.entity_id ASC LIMIT 1',
            );
            if ($row) {
                return ['orderId' => (int) $row['order_id'], 'invoiceId' => (int) $row['invoice_id']];
            }
        } catch (\Throwable $e) {
            // Fall through to null.
        }
        return null;
    }

    /**
     * Seed sales data the read/lifecycle tests act on. Sample data ships no
     * orders, so create (once) a small, self-consistent set via the model layer:
     * a fresh customer order (holdable/cancellable), an invoiced+shipped customer
     * order, a guest order+invoice (the cross-tenant "foreign" fixture), a gift
     * card and an approved product review. Every step is best-effort - a failure
     * leaves the corresponding fixtures null and the dependent tests skip, exactly
     * as before, rather than erroring the suite.
     *
     * @return array<string, mixed>
     */
    private static function seedSalesData(?int $customerId, ?string $sku): array
    {
        $seed = [
            'order_id' => null,
            'giftcard_code' => null,
            'giftcard_id' => null,
            'review_product_id' => null,
        ];
        if (!$customerId || !$sku) {
            return $seed;
        }

        try {
            \Mage::app()->getStore(1)->resetConfig();

            // A fresh order: holdable, cancellable, and the generic order_id.
            // Capture its id: earlier suites leave orders created directly via
            // models (no quote), and an unordered "first order" lookup picks one
            // of those on PostgreSQL, where heap order is not insertion order.
            $fresh = self::placeSeedOrder($customerId, $sku, false);
            if ($fresh?->getId()) {
                $seed['order_id'] = (int) $fresh->getId();
            }

            // An invoiced-but-unshipped customer order: drives customer_order_id
            // AND stays shippable for the shipment-creation tests.
            $invoiced = self::placeSeedOrder($customerId, $sku, false);
            if ($invoiced) {
                self::invoiceSeedOrder($invoiced);
            }

            // An invoiced + shipped order so shipment-track tests find a shipment.
            $shipped = self::placeSeedOrder($customerId, $sku, false);
            if ($shipped) {
                self::invoiceSeedOrder($shipped);
                self::shipSeedOrder($shipped);
            }

            // A guest order + invoice: the cross-tenant "foreign" fixture.
            $guest = self::placeSeedOrder($customerId, $sku, true);
            if ($guest) {
                self::invoiceSeedOrder($guest);
            }
        } catch (\Throwable $e) {
            // Best effort - dependent tests skip if an order couldn't be seeded.
        }

        $giftcard = self::seedGiftcard();
        $seed['giftcard_code'] = $giftcard['code'];
        $seed['giftcard_id'] = $giftcard['id'];
        $seed['review_product_id'] = self::seedProductReview($sku);
        self::seedRevocationRequest();

        return $seed;
    }

    /**
     * Seed a revocation ("right to be forgotten") request with a foreign email so
     * the admin-read and cross-customer-hide read tests have a row to act on.
     */
    private static function seedRevocationRequest(): void
    {
        if (!class_exists('Maho_Revocation_Model_Request')) {
            return;
        }
        try {
            \Mage::getModel('revocation/request')
                ->setStoreId(1)
                ->setOrderReference('SEED-REV-1')
                ->setCustomerName('Seed Foreign')
                ->setEmail('seed.foreign.revoke@example.com')
                ->setReason('Seeded for API read coverage')
                ->setVerified(0)
                ->setReceivedAt((string) \Mage::app()->getLocale()->formatDateForDb(time()))
                ->save();
        } catch (\Throwable $e) {
            // best effort
        }
    }

    /**
     * Place a real order for a customer (or a guest) through the quote service,
     * mirroring a storefront checkout. Returns the order, or null on any failure.
     */
    private static function placeSeedOrder(?int $customerId, string $sku, bool $guest): ?\Mage_Sales_Model_Order
    {
        try {
            $productId = \Mage::getModel('catalog/product')->getIdBySku($sku);
            if (!$productId) {
                return null;
            }
            $product = \Mage::getModel('catalog/product')->load($productId);

            $regionId = (int) \Mage::getModel('directory/region')->loadByCode('CA', 'US')->getId();
            $email = 'seed.' . ($guest ? 'guest' : 'cust') . '@example.com';
            $address = [
                'firstname' => 'Seed',
                'lastname' => 'Buyer',
                'street' => '123 Seed St',
                'city' => 'Los Angeles',
                'region_id' => $regionId,
                'region' => 'California',
                'postcode' => '90210',
                'country_id' => 'US',
                'telephone' => '5550100',
                'email' => $email,
            ];

            $quote = \Mage::getModel('sales/quote')->setStoreId(1);
            if ($guest) {
                $quote->setCustomerIsGuest(true)->setCustomerEmail($email);
            } else {
                $customer = \Mage::getModel('customer/customer')->load($customerId);
                if (!$customer->getId()) {
                    return null;
                }
                $quote->assignCustomer($customer);
                // Be explicit so the placed order is unambiguously customer-owned
                // (customer_id set, not a guest) for the ownership-scoped tests.
                $quote->setCustomerId((int) $customer->getId())
                    ->setCustomerIsGuest(false)
                    ->setCustomerEmail($customer->getEmail());
            }
            $quote->addProduct($product, new \Maho\DataObject(['qty' => 1]));
            $quote->getBillingAddress()->addData($address);
            $shipping = $quote->getShippingAddress()->addData($address);
            $shipping->setCollectShippingRates(true)->setShippingMethod('freeshipping_freeshipping');
            $quote->getPayment()->importData(['method' => 'cashondelivery']);
            $quote->collectTotals()->save();

            $service = new \Mage_Sales_Model_Service_Quote($quote);
            $service->submitOrder();
            $order = $service->getOrder();

            return $order && $order->getId() ? $order : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function invoiceSeedOrder(\Mage_Sales_Model_Order $order): void
    {
        try {
            if (!$order->canInvoice()) {
                return;
            }
            $invoice = \Mage::getModel('sales/service_order', $order)->prepareInvoice();
            if (!$invoice->getTotalQty()) {
                return;
            }
            $invoice->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            \Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($order)
                ->save();
        } catch (\Throwable $e) {
            // best effort
        }
    }

    private static function shipSeedOrder(\Mage_Sales_Model_Order $order): void
    {
        try {
            if (!$order->canShip()) {
                return;
            }
            $shipment = $order->prepareShipment();
            if (!$shipment || !$shipment->getTotalQty()) {
                return;
            }
            $shipment->register();
            \Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($order)
                ->save();
        } catch (\Throwable $e) {
            // best effort
        }
    }

    /**
     * @return array{code: ?string, id: ?int}
     */
    private static function seedGiftcard(): array
    {
        if (!class_exists('Maho_Giftcard_Model_Giftcard')) {
            return ['code' => null, 'id' => null];
        }
        try {
            $code = \Mage::helper('giftcard')->generateCode();
            $giftcard = \Mage::getModel('giftcard/giftcard')
                ->setCode($code)
                ->setStatus(\Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
                ->setWebsiteId(1)
                ->setBalance(100.00)
                ->setInitialBalance(100.00);
            $giftcard->save();
            return ['code' => $code, 'id' => (int) $giftcard->getId()];
        } catch (\Throwable $e) {
            return ['code' => null, 'id' => null];
        }
    }

    private static function seedProductReview(string $sku): ?int
    {
        try {
            $productId = (int) \Mage::getModel('catalog/product')->getIdBySku($sku);
            if (!$productId) {
                return null;
            }
            $review = \Mage::getModel('review/review')->setData([
                'title' => 'Seeded review',
                'nickname' => 'Seed Reviewer',
                'detail' => 'A seeded, approved review for API read coverage.',
            ]);
            $review->setEntityId($review->getEntityIdByCode(\Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue($productId)
                ->setStatusId(\Mage_Review_Model_Review::STATUS_APPROVED)
                ->setStoreId(1)
                ->setStores([1])
                ->save();
            $review->aggregate();
            return $productId;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function lookupCustomerEmail(int $customerId): string
    {
        try {
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            return $customer->getEmail() ?: 'test@example.com';
        } catch (\Throwable $e) {
            return 'test@example.com';
        }
    }
}
