<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests;

use Tests\Api\Client\JsonRpcClient;
use Tests\Api\Client\Response\JsonRpcResponse;

abstract class MahoApiTestCase extends \Tests\MahoBackendTestCase
{
    protected JsonRpcClient $apiClient;
    protected ?string $sessionId = null;
    protected array $apiConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // The legacy Mage_Api endpoints are opt-in (disabled by default). Enable
        // the ones these tests exercise once per run; the flags persist in
        // core_config_data. Done here rather than relying on the Api/V2 suite,
        // which enables them too but runs alphabetically after Api/JsonRpc.
        static $legacyProtocolsEnabled = false;
        if (!$legacyProtocolsEnabled) {
            $config = \Mage::getModel('core/config');
            $config->saveConfig('apiplatform/protocols/legacy_rest', '1', 'default', 0);
            $config->saveConfig('apiplatform/protocols/jsonrpc', '1', 'default', 0);
            \Mage::app()->getCache()->cleanType('config');
            $legacyProtocolsEnabled = true;
        }

        // Skip if no API server configured (CI environments)
        if (empty($_ENV['API_BASE_URL'])) {
            try {
                $store = \Mage::app()->getStore();
                $baseUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);
                if (!$baseUrl || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                    $this->markTestSkipped('API server not configured');
                }
            } catch (\Throwable $e) {
                $this->markTestSkipped('API server not available: ' . $e->getMessage());
            }
        }

        $this->apiConfig = $this->getApiConfig();
        $this->apiClient = new JsonRpcClient($this->apiConfig['base_url']);

        // Set up basic auth if configured
        if (!empty($this->apiConfig['username']) && !empty($this->apiConfig['password'])) {
            $this->apiClient->withBasicAuth($this->apiConfig['username'], $this->apiConfig['password']);
        }

        $this->apiClient->withTimeout($this->apiConfig['timeout'] ?? 30);
    }

    protected function tearDown(): void
    {
        if ($this->sessionId) {
            try {
                $this->apiClient->call('endSession', [], $this->sessionId);
            } catch (\Exception $e) {
                // Ignore session cleanup errors
            }
            $this->sessionId = null;
        }

        parent::tearDown();
    }

    /**
     * Get API configuration from environment or defaults
     */
    protected function getApiConfig(): array
    {
        return [
            'base_url' => $this->getApiBaseUrl(),
            'username' => $_ENV['API_USERNAME'] ?? 'test_api_user',
            'password' => $_ENV['API_PASSWORD'] ?? 'test_api_password_123',
            'timeout' => (int) ($_ENV['API_TIMEOUT'] ?? 30),
        ];
    }

    /**
     * Get API base URL from Maho configuration
     */
    protected function getApiBaseUrl(): string
    {
        // Allow override via environment variable
        if (!empty($_ENV['API_BASE_URL'])) {
            return $_ENV['API_BASE_URL'];
        }

        try {
            // Method 1: Try to get from current store configuration
            $store = \Mage::app()->getStore();
            $baseUrl = $store->getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_WEB);

            // If we got a valid URL, use it
            if ($baseUrl && filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $baseUrl = rtrim($baseUrl, '/');
                return $baseUrl . '/api.php';
            }

            // Method 2: Try to get from configuration directly
            $unsecureBaseUrl = \Mage::getStoreConfig('web/unsecure/base_url');
            $secureBaseUrl = \Mage::getStoreConfig('web/secure/base_url');

            // Prefer HTTPS if available, otherwise HTTP
            $configuredUrl = $secureBaseUrl ?: $unsecureBaseUrl;

            if ($configuredUrl && filter_var($configuredUrl, FILTER_VALIDATE_URL)) {
                $configuredUrl = rtrim($configuredUrl, '/');
                return $configuredUrl . '/api.php';
            }

            // Method 3: Try to detect from server environment (for local development)
            if (isset($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $baseDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
                $baseDir = ($baseDir === '/') ? '' : $baseDir;

                return $scheme . '://' . $host . $baseDir . '/api.php';
            }

        } catch (\Exception $e) {
            // Log error but don't fail the test setup
            \Mage::log('Failed to detect API base URL: ' . $e->getMessage(), \Mage::LOG_WARNING);
        }

        // Final fallback
        return 'http://localhost/api.php';
    }

    /**
     * Login and get session ID for authenticated API calls
     */
    protected function getAuthenticatedSessionId(): string
    {
        if ($this->sessionId === null) {
            $this->sessionId = $this->apiClient->login(
                $this->apiConfig['username'],
                $this->apiConfig['password'],
            );
        }

        return $this->sessionId;
    }

    /**
     * Make an authenticated API call
     */
    protected function authenticatedCall(string $method, array $params = []): JsonRpcResponse
    {
        $sessionId = $this->getAuthenticatedSessionId();
        return $this->apiClient->call($method, $params, $sessionId);
    }

    /**
     * Assert that a JSON-RPC response is successful
     */
    protected function assertSuccessfulResponse(JsonRpcResponse $response, string $message = ''): void
    {
        if (!$response->isSuccess()) {
            $error = $response->getError();
            $errorMessage = $error['message'] ?? 'Unknown API error';
            $fullMessage = $message ? "{$message}: {$errorMessage}" : $errorMessage;

            $this->fail($fullMessage . " (HTTP: {$response->getHttpCode()})");
        }

        $this->assertTrue($response->isSuccess(), $message);
    }

    /**
     * Assert that a JSON-RPC response contains an error
     */
    protected function assertErrorResponse(JsonRpcResponse $response, ?string $expectedMessage = null): void
    {
        $this->assertFalse($response->isSuccess(), 'Expected error response but got success');
        $this->assertTrue($response->hasError(), 'Expected error in response');

        if ($expectedMessage !== null) {
            $error = $response->getError();
            $actualMessage = $error['message'] ?? '';
            $this->assertStringContainsString($expectedMessage, $actualMessage);
        }
    }

    /**
     * Assert that response result has expected structure
     */
    protected function assertResponseStructure(JsonRpcResponse $response, array $structure): void
    {
        $this->assertSuccessfulResponse($response);
        $result = $response->getResult();

        foreach ($structure as $key => $type) {
            if (is_numeric($key)) {
                // Numeric key means we're checking if a key exists
                $this->assertArrayHasKey($type, $result, "Expected key '{$type}' in response");
            } else {
                // String key means we're checking key existence and type
                $this->assertArrayHasKey($key, $result, "Expected key '{$key}' in response");

                if ($type === 'array') {
                    $this->assertIsArray($result[$key], "Expected '{$key}' to be array");
                } elseif ($type === 'string') {
                    $this->assertIsString($result[$key], "Expected '{$key}' to be string");
                } elseif ($type === 'int') {
                    $this->assertIsInt($result[$key], "Expected '{$key}' to be integer");
                } elseif ($type === 'bool') {
                    $this->assertIsBool($result[$key], "Expected '{$key}' to be boolean");
                }
            }
        }
    }

    /**
     * Create test data for API operations (override in specific tests)
     */
    protected function createTestData(): array
    {
        return [];
    }

    /**
     * Clean up test data created during tests (override in specific tests)
     */
    protected function cleanupTestData(array $testData): void
    {
        // Override in specific test classes
    }

    /**
     * Skip test if API is not available
     */
    protected function skipIfApiNotAvailable(): void
    {
        try {
            // Any well-formed JSON-RPC reply proves the endpoint is reachable -
            // even an auth error like "Session expired" (resources requires a
            // session). Only a transport failure or a non-JSON-RPC body (which
            // makes the response object throw) means the API is truly unavailable.
            $this->apiClient->call('resources');
        } catch (\Exception $e) {
            $this->markTestSkipped('API is not available at: ' . $this->apiConfig['base_url'] . ' - ' . $e->getMessage());
        }
    }

    /**
     * Get the detected API URL for debugging
     */
    protected function getDetectedApiUrl(): string
    {
        return $this->getApiBaseUrl();
    }

    /**
     * Setup API user with minimal blog permissions
     * This ensures the test user has only the required blog API permissions
     */
    protected function setupBlogApiUser(): void
    {
        $username = $this->apiConfig['username'];
        $password = $this->apiConfig['password'];

        // Create the API user if missing. The credential is the api_key (hashed
        // by Mage_Api_Model_User::_beforeSave), not the admin-style password.
        $user = \Mage::getModel('api/user')->loadByUsername($username);
        if (!$user->getId()) {
            $user = \Mage::getModel('api/user');
            $user->setData([
                'username' => $username,
                'firstname' => 'Blog',
                'lastname' => 'API User',
                'email' => 'blog-api-test@example.com',
                'api_key' => $password,
                'api_key_confirmation' => $password,
                'is_active' => 1,
            ]);
            $user->save();
        }

        $this->ensureBlogApiRole($user);
    }

    /**
     * Give the API user a group role granting the blog_post resources.
     *
     * Mirrors the admin Api/RoleController flow: a group ('G') role holds the
     * ACL rules, the user is attached to it via a 'U' assignment row, and
     * Mage_Api_Model_Session::login() requires that assignment (parent_id > 0)
     * before it will authorise any call.
     */
    private function ensureBlogApiRole($user): void
    {
        $roleName = 'Blog API Test Role';

        $group = \Mage::getModel('api/role')->getCollection()
            ->addFieldToFilter('role_type', \Mage_Api_Model_Acl::ROLE_TYPE_GROUP)
            ->addFieldToFilter('role_name', $roleName)
            ->getFirstItem();

        if (!$group->getId()) {
            $group = \Mage::getModel('api/role')
                ->setName($roleName)
                ->setPid(0)
                ->setRoleType(\Mage_Api_Model_Acl::ROLE_TYPE_GROUP)
                ->save();
        }

        // Allow the blog_post resource tree (deny everything else). The method
        // ACLs are blog/post/{list,info,create,update,delete}.
        \Mage::getModel('api/rules')
            ->setRoleId($group->getId())
            ->setResources([
                'blog', 'blog/post',
                'blog/post/list', 'blog/post/info',
                'blog/post/create', 'blog/post/update', 'blog/post/delete',
            ])
            ->saveRel();

        // Attach the user to the group role (idempotent).
        $user->setRoleId($group->getId())->setUserId($user->getId());
        if ($user->roleUserExists() !== true) {
            $user->add();
        }
    }
}
