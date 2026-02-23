<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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

        } catch (Exception $e) {
            // Log error but don't fail the test setup
            error_log('Failed to detect API base URL: ' . $e->getMessage());
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
            $response = $this->apiClient->call('resources');
            if (!$response->isSuccess()) {
                $this->markTestSkipped('API is not available or not responding at: ' . $this->apiConfig['base_url']);
            }
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

        // Check if user already exists
        $existingUser = \Mage::getModel('api/user')->loadByUsername($username);

        if ($existingUser->getId()) {
            // User exists, just ensure correct permissions
            $this->ensureBlogApiPermissions($existingUser);
            return;
        }

        // Create new API user with minimal blog permissions
        $user = \Mage::getModel('api/user');
        $user->setData([
            'username' => $username,
            'firstname' => 'Blog',
            'lastname' => 'API User',
            'email' => 'blog-api-test@example.com',
            'password' => $password,
            'is_active' => 1,
        ]);
        $user->save();

        // Create minimal role with only blog API permissions
        $role = \Mage::getModel('api/role');
        $role->setData([
            'role_name' => 'Blog API Test Role',
            'role_type' => 'U',
            'user_id' => $user->getId(),
        ]);
        $role->save();

        // Set blog-specific API permissions
        $this->setBlogApiPermissions($role);
    }

    /**
     * Ensure existing user has correct blog API permissions
     */
    private function ensureBlogApiPermissions($user): void
    {
        $roles = \Mage::getModel('api/role')->getCollection()
            ->addFieldToFilter('user_id', $user->getId())
            ->addFieldToFilter('role_type', 'U');

        foreach ($roles as $role) {
            $this->setBlogApiPermissions($role);
        }
    }

    /**
     * Set minimal blog API permissions for a role
     */
    private function setBlogApiPermissions($role): void
    {
        // Delete existing permissions for this role
        \Mage::getModel('api/rules')->getCollection()
            ->addFieldToFilter('role_id', $role->getId())
            ->walk('delete');

        // Add only blog API permissions
        $blogPermissions = [
            'system/api/blog_post', // Blog API resource
        ];

        foreach ($blogPermissions as $permission) {
            $rule = \Mage::getModel('api/rules');
            $rule->setData([
                'role_id' => $role->getId(),
                'resource_id' => $permission,
                'privileges' => null, // Grant all privileges for this resource
            ]);
            $rule->save();
        }
    }
}
