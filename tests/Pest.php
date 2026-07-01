<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

// For frontend tests:
// uses(Tests\MahoFrontendTestCase::class);

// For backend tests:
// uses(Tests\MahoBackendTestCase::class);

// For installation tests:
// uses(Tests\MahoInstallTestCase::class);

/*
|--------------------------------------------------------------------------
| API v2 Helper Functions
|--------------------------------------------------------------------------
|
| Global functions wrapping ApiV2Helper for concise test syntax.
| Used by tests in tests/Api/V2/
|
*/

function apiGet(string $path, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::get($path, $token, $extraHeaders);
}

function apiPost(string $path, array $data, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::post($path, $data, $token, $extraHeaders);
}

function apiPut(string $path, array $data, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::put($path, $data, $token, $extraHeaders);
}

function apiDelete(string $path, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::delete($path, $token, $extraHeaders);
}

function apiOptions(string $path, array $extraHeaders = []): array
{
    return ApiV2Helper::options($path, $extraHeaders);
}

function apiHeader(array $response, string $name): ?string
{
    return ApiV2Helper::headerValue($response, $name);
}

function apiPostMultipart(string $path, array $fields, array $files, ?string $token = null): array
{
    return ApiV2Helper::postMultipart($path, $fields, $files, $token);
}

function gqlQuery(string $query, array $variables = [], ?string $token = null): array
{
    return ApiV2Helper::graphql($query, $variables, $token);
}

function customerToken(?int $customerId = null): string
{
    return ApiV2Helper::generateCustomerToken($customerId);
}

function adminToken(): string
{
    return ApiV2Helper::generateAdminToken();
}

function expiredToken(): string
{
    return ApiV2Helper::generateExpiredToken();
}

function invalidToken(): string
{
    return ApiV2Helper::generateInvalidToken();
}

/**
 * Generate a service account JWT with specific permissions.
 * Used for testing permission enforcement on write endpoints.
 *
 * @param array $permissions e.g. ['cms-pages/write', 'cms-pages/delete'] or ['all']
 * @param array|null $storeIds Allowed store IDs, null for all stores
 */
function serviceToken(array $permissions = ['all'], ?array $storeIds = null): string
{
    return ApiV2Helper::generateToken([
        'sub' => 'api_user_test',
        'type' => 'api_user',
        // No api_user_id - use JWT-embedded permissions for testing
        'permissions' => $permissions,
        'allowed_store_ids' => $storeIds,
    ]);
}

function fixtures(string $key): mixed
{
    return ApiV2Helper::fixtures($key);
}

function trackCreated(string $type, int $id): void
{
    ApiV2Helper::trackCreated($type, $id);
}

function cleanupTestData(): void
{
    ApiV2Helper::cleanup();
}

/**
 * Helper to extract items from API Platform collection responses.
 * Handles both JSON-LD (hydra:member / member) and plain array formats.
 */
function getItems(array $response): array
{
    $json = $response['json'] ?? [];
    if (isset($json['member'])) {
        return $json['member'];
    }
    if (isset($json['hydra:member'])) {
        return $json['hydra:member'];
    }
    if (is_array($json) && (empty($json) || isset($json[0]))) {
        return $json;
    }
    return [];
}

/*
|--------------------------------------------------------------------------
| API v2 Test Availability Check
|--------------------------------------------------------------------------
|
| Skip Api/V2 tests when no API server is reachable.
| CI environments install Maho but do not start a web server,
| so these integration tests can only run locally or in environments
| where the API is served.
|
*/


uses()
    ->beforeEach(function (): void {
        static $apiAvailable = null;
        static $protocolsEnabled = false;

        // API protocols default to disabled (opt-in security model). Tests
        // need them on, so flip every flag once at suite start. Writes to
        // core_config_data persist for the test run; the suite assumes an
        // ephemeral test database anyway.
        if (!$protocolsEnabled) {
            // Frontend/Backend tearDown calls Mage::reset(), so re-bootstrap before DB writes.
            \Mage::app();
            $protocols = ['rest_v2', 'graphql', 'admin_graphql', 'legacy_rest', 'soap', 'v2_soap', 'xmlrpc', 'jsonrpc'];
            $config = \Mage::getModel('core/config');
            foreach ($protocols as $protocol) {
                $config->saveConfig('apiplatform/protocols/' . $protocol, '1', 'default', 0);
            }
            \Mage::app()->getCache()->cleanType('config');
            $protocolsEnabled = true;
        }

        if ($apiAvailable === null) {
            try {
                $baseUrl = ApiV2Helper::getBaseUrlPublic();
                $ch = curl_init($baseUrl . '/api/rest/v2/store-config');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $apiAvailable = ($code !== 0);
            } catch (\Throwable) {
                $apiAvailable = false;
            }
        }
        if (!$apiAvailable) {
            $this->markTestSkipped('API server not reachable - skipping API integration tests');
        }
    })
    ->in('Api/V2');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeUnauthorized', function () {
    return $this->toBe(401);
});

expect()->extend('toBeForbidden', function () {
    return $this->toBe(403);
});

expect()->extend('toBeNotFound', function () {
    return $this->toBeIn([404, 410]);
});

expect()->extend('toBeSuccessful', function () {
    return $this->toBeGreaterThanOrEqual(200)->toBeLessThan(300);
});

/**
 * Whether the real-browser (Pest browser plugin / Playwright) toolchain is available.
 * The browser test suite runs as part of the normal pest run, but skips cleanly anywhere
 * Playwright isn't installed (e.g. CI matrix jobs that don't provision it), so it never
 * fails those environments. The plugin launches ./node_modules/.bin/playwright.
 */
function browserTestsReady(): bool
{
    return is_file(dirname(__DIR__) . '/node_modules/.bin/playwright');
}
