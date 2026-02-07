<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
| Used by tests in tests/Feature/Api/V2/
|
*/

function apiGet(string $path, ?string $token = null): array
{
    return ApiV2Helper::get($path, $token);
}

function apiPost(string $path, array $data, ?string $token = null): array
{
    return ApiV2Helper::post($path, $data, $token);
}

function apiPut(string $path, array $data, ?string $token = null): array
{
    return ApiV2Helper::put($path, $data, $token);
}

function apiDelete(string $path, ?string $token = null): array
{
    return ApiV2Helper::delete($path, $token);
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
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeUnauthorized', function () {
    return $this->toBe(401);
});

expect()->extend('toBeNotFound', function () {
    return $this->toBeIn([404, 410]);
});

expect()->extend('toBeSuccessful', function () {
    return $this->toBeGreaterThanOrEqual(200)->toBeLessThan(300);
});
