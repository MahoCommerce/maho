<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maho\ApiPlatform\Service\JwtService;

/**
 * API v2 Test Helper
 *
 * Provides HTTP client methods, JWT token generation, and test fixtures
 * for integration testing the API Platform REST and GraphQL endpoints.
 */
class ApiV2Helper
{
    private static ?string $baseUrl = null;
    private static ?string $jwtSecret = null;
    private static ?Configuration $jwtConfig = null;

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
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function get(string $path, ?string $token = null): array
    {
        return self::request('GET', $path, null, $token);
    }

    /**
     * HTTP POST request
     *
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function post(string $path, array $data, ?string $token = null): array
    {
        return self::request('POST', $path, $data, $token);
    }

    /**
     * HTTP PUT request
     *
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function put(string $path, array $data, ?string $token = null): array
    {
        return self::request('PUT', $path, $data, $token);
    }

    /**
     * HTTP DELETE request
     *
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    public static function delete(string $path, ?string $token = null): array
    {
        return self::request('DELETE', $path, null, $token);
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
            ->issuedBy($claims['iss'] ?? self::getBaseUrl() . '/')
            ->permittedFor($claims['aud'] ?? 'maho-api')
            ->identifiedBy($claims['jti'] ?? bin2hex(random_bytes(16)))
            ->issuedAt($now)
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
            'roles' => ['ROLE_USER'],
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
            ->issuedBy(self::getBaseUrl() . '/')
            ->permittedFor('maho-api')
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->relatedTo('customer_1')
            ->issuedAt($past)
            ->expiresAt($past->modify('+1 day')) // expired 1 day ago
            ->withClaim('customer_id', 1)
            ->withClaim('type', 'customer')
            ->withClaim('roles', ['ROLE_USER'])
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
            'roles' => ['ROLE_USER'],
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
    private static function ensureMahoBootstrapped(): void
    {
        static $bootstrapped = false;
        if (!$bootstrapped) {
            try {
                \Mage::app();
                $bootstrapped = true;
            } catch (\Throwable $e) {
                // Unable to bootstrap — DB lookups will use fallbacks
            }
        }
    }

    /**
     * Get a test fixture value
     */
    public static function fixtures(string $key): mixed
    {
        static $fixtures = null;

        if ($fixtures === null) {
            self::ensureMahoBootstrapped();

            $productData = self::lookupProduct();
            $configurableSku = self::lookupConfigurableSku();
            $categoryId = self::lookupCategoryId();

            $fixtures = [
                'customer_id' => self::lookupCustomerId(),
                'customer_email' => self::lookupCustomerEmail(self::lookupCustomerId()),
                'invalid_customer_id' => 999999,
                'product_id' => $productData['id'],
                'product_sku' => $productData['sku'],
                'configurable_sku' => $configurableSku,
                'category_id' => $categoryId,
                'invalid_product_id' => 999999,
                'existing_cart_id' => null,
                'order_id' => self::lookupOrderId(),
                'invalid_order_id' => 999999,
                'write_test_sku' => $productData['sku'],
                'write_test_qty' => 1,
                'blog_post_url_key' => null,
            ];
        }

        return $fixtures[$key] ?? null;
    }

    /**
     * Make an HTTP request to the API
     *
     * @return array{status: int, json: array, raw: string, headers: array}
     */
    private static function request(string $method, string $path, ?array $data = null, ?string $token = null): array
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
            $options['http']['content'] = json_encode($data);
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

        if (!empty($_ENV['API_BASE_URL'])) {
            self::$baseUrl = rtrim($_ENV['API_BASE_URL'], '/');
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
     * Get JWT secret from Maho configuration
     */
    private static function getJwtSecret(): string
    {
        if (self::$jwtSecret !== null) {
            return self::$jwtSecret;
        }

        try {
            self::ensureMahoBootstrapped();
            $jwtService = new JwtService();
            self::$jwtSecret = $jwtService->getSecret();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Cannot get JWT secret: ' . $e->getMessage());
        }

        return self::$jwtSecret;
    }

    private static function lookupCustomerId(): ?int
    {
        try {
            $customer = \Mage::getModel('customer/customer')->getCollection()
                ->addFieldToFilter('is_active', 1)
                ->setPageSize(1)
                ->getFirstItem();
            return $customer->getId() ? (int) $customer->getId() : 1;
        } catch (\Throwable $e) {
            return 1;
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
            $category = \Mage::getModel('catalog/category')->getCollection()
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
                ->setPageSize(1)
                ->getFirstItem();
            return $order->getId() ? (int) $order->getId() : null;
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
