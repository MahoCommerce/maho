<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

// Autoload module API classes (Mage\Foo\Api\Bar, Maho\Foo\Api\Bar) in tests.
// At runtime the Symfony API kernel registers this PSR-4-style mapping; the
// plain Mage bootstrap used by Backend/Frontend tests does not, so without this
// model-level integration tests cannot instantiate API services/processors.
// Namespace mirrors the path under app/code/core/. Maho\ApiPlatform\* base
// classes are composer-PSR-4 and are intentionally not matched here.
spl_autoload_register(function (string $class): void {
    if (!preg_match('#^(Mage|Maho)\\\\[A-Za-z0-9_]+\\\\Api\\\\#', $class)) {
        return;
    }
    $file = dirname(__DIR__) . '/app/code/core/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

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

function apiGetRaw(string $path, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::getRaw($path, $token, $extraHeaders);
}

function apiPost(string $path, array $data, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::post($path, $data, $token, $extraHeaders);
}

function apiPostRaw(string $path, string $body, ?string $token = null, array $extraHeaders = []): array
{
    return ApiV2Helper::postRaw($path, $body, $token, $extraHeaders);
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

/**
 * Mirrors Maho\ApiPlatform\Kernel::isMcpAvailable(). Without the packages the
 * endpoint is never routed, so the tests would report 404s, not a missing feature.
 */
function mcpPackagesInstalled(): bool
{
    return class_exists(\Symfony\AI\McpBundle\McpBundle::class)
        && \Composer\InstalledVersions::isInstalled('psr/http-factory-implementation');
}

function mcpSession(?string $token = null): ?string
{
    return ApiV2Helper::mcpSession($token);
}

function mcpCall(array $payload, ?string $token = null, ?string $sessionId = null): array
{
    return ApiV2Helper::mcp($payload, $token, $sessionId);
}

function mcpTool(string $name, array $arguments, ?string $token, ?string $sessionId, array $extraHeaders = []): array
{
    return ApiV2Helper::mcpTool($name, $arguments, $token, $sessionId, $extraHeaders);
}

function mcpTools(?string $token, ?string $sessionId): array
{
    return ApiV2Helper::mcpTools($token, $sessionId);
}

function customerToken(?int $customerId = null): string
{
    return ApiV2Helper::generateCustomerToken($customerId);
}

function adminToken(): string
{
    return ApiV2Helper::generateAdminToken();
}

/**
 * Create an admin role granting only the listed ACL paths, plus an admin
 * user assigned to that role. Returns a JWT for the user.
 *
 * @param list<string> $allowedAclPaths e.g. ['catalog/products', 'sales']
 */
function adminTokenWithAcl(array $allowedAclPaths, string $username): string
{
    ApiV2Helper::ensureMahoBootstrapped();

    // The JWT secret is auto-generated on the kernel's first HTTP boot.
    // Trigger it with a public no-auth request before issuing tokens,
    // mirrors what apiGet/apiPost do implicitly in other tests, but those
    // tests issue tokens after their first HTTP call.
    static $kernelBooted = false;
    if (!$kernelBooted) {
        apiGet('/api/rest/v2/store-config');
        Mage::app()->getCache()->cleanType('config');
        Mage::app()->reinitStores();
        $kernelBooted = true;
    }

    /** @var Mage_Admin_Model_Role $role */
    $role = Mage::getModel('admin/role');
    $role->setData([
        'role_name' => $username . '_role',
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ])->save();
    trackCreated('admin_role', (int) $role->getId());

    Mage::getModel('admin/rules')
        ->setRoleId($role->getId())
        ->setResources($allowedAclPaths)
        ->saveRel();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Pest',
        'lastname' => 'Acl',
        'email' => $username . '@example.test',
        'password' => 'pest-acl-password-1234',
        'is_active' => 1,
    ])->save();
    trackCreated('admin_user', (int) $user->getId());

    Mage::getModel('admin/user')
        ->setRoleId($role->getId())
        ->setUserId($user->getId())
        ->add();

    return ApiV2Helper::generateToken([
        'sub' => 'admin_' . $user->getId(),
        'admin_id' => (int) $user->getId(),
        'email' => $user->getEmail(),
        'type' => 'admin',
        'roles' => ['ROLE_ADMIN'],
    ]);
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
    return ApiV2Helper::generateServiceToken($permissions, $storeIds);
}

/**
 * Like serviceToken(), but with an explicit caller identity (JWT `sub`). Two
 * calls with different identities are two distinct API callers — used to prove
 * per-caller scoping (e.g. idempotency replay isolation).
 */
function serviceTokenAs(string $identity, array $permissions = ['all'], ?array $storeIds = null): string
{
    return ApiV2Helper::generateServiceToken($permissions, $storeIds, $identity);
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
    // API protocols default to disabled (opt-in security model). Tests need them
    // on, so flip every flag once at suite start. Writes to core_config_data
    // persist for the test run; the suite assumes an ephemeral test database.
    //
    // This runs in beforeAll (setUpBeforeClass), not beforeEach, on purpose:
    // Mage::app() registers a custom error handler via _initEnvironment(), and
    // PHPUnit's per-test snapshot flags a test as risky if the error-handler
    // stack changes during setUp/test/tearDown. beforeAll runs outside that
    // snapshot, so the one-time bootstrap doesn't leak into any single test.
    ->beforeAll(function (): void {
        static $protocolsEnabled = false;
        if ($protocolsEnabled) {
            return;
        }
        // Frontend/Backend tearDown calls Mage::reset(), so re-bootstrap before DB writes.
        \Mage::app();
        $config = \Mage::getModel('core/config');

        $protocols = ['rest_v2', 'graphql', 'admin_graphql', 'mcp', 'legacy_rest', 'soap', 'v2_soap', 'xmlrpc', 'jsonrpc'];
        foreach ($protocols as $protocol) {
            $config->saveConfig('apiplatform/protocols/' . $protocol, '1', 'default', 0);
        }

        // Enable a minimal offline checkout path (free shipping + cash on
        // delivery) so the order-placement / credit-memo / shipment write tests
        // can drive a real checkout instead of skipping. These default off.
        $checkout = [
            'carriers/freeshipping/active' => '1',
            'carriers/flatrate/active' => '1',
            'payment/cashondelivery/active' => '1',
            'payment/checkmo/active' => '1',
        ];
        foreach ($checkout as $path => $value) {
            $config->saveConfig($path, $value, 'default', 0);
        }

        \Mage::app()->getCache()->cleanType('config');
        $protocolsEnabled = true;
    })
    ->beforeEach(function (): void {
        static $apiAvailable = null;

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

uses()
    ->beforeEach(function (): void {
        if (!mcpPackagesInstalled()) {
            $this->markTestSkipped('MCP packages not installed - run composer require symfony/mcp-bundle nyholm/psr7');
        }
    })
    ->in('Api/V2/Mcp');

uses()
    // The Api/V2 suite above runs before this one in the same test database and
    // enables extra carriers, payment methods and gift options for its checkout
    // write tests (see its beforeAll and GiftMessageTest). The storefront one-step
    // checkout auto-selects the shipping method only when exactly one rate comes
    // back, so a leaked freeshipping carrier leaves two unselected methods and the
    // payment step never loads. Delete the default-scope overrides so the real
    // storefront drives on install defaults again.
    ->beforeAll(function (): void {
        static $reverted = false;
        if ($reverted) {
            return;
        }
        \Mage::app();
        $config = \Mage::getModel('core/config');
        $leaked = [
            'carriers/freeshipping/active',
            'carriers/flatrate/active',
            'payment/cashondelivery/active',
            'payment/checkmo/active',
            'sales/gift_options/allow_order',
            'sales/gift_options/allow_items',
        ];
        foreach ($leaked as $path) {
            $config->deleteConfig($path, 'default', 0);
        }
        \Mage::app()->getCache()->cleanType('config');
        $reverted = true;
    })
    ->in('Browser');

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

/**
 * Block until the browser has finished loading the page $selector belongs to.
 *
 * The plugin's own waits are no-ops (waitForEvent/waitForFunction never iterate the generator
 * Client::execute() returns) and navigate() can return on the previous navigation's load event.
 * $selector has to belong to the awaited page and not the one being left, since about:blank and
 * the previous page both report readyState 'complete'; a bare tag name reads as link text.
 */
function waitForPageLoad(object $page, string $selector): object
{
    $page->text($selector);

    $deadline = microtime(true) + 5;
    while ($page->script('document.readyState') !== 'complete') {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("The page holding [{$selector}] did not finish loading within 5s");
        }
        usleep(50_000);
    }

    return $page;
}

/*
|--------------------------------------------------------------------------
| Message Queue Helper Functions
|--------------------------------------------------------------------------
|
| Shared by tests in tests/Backend/Integration/Queue/.
|
*/

function queueAdapter(): \Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function clearQueueTable(): void
{
    queueAdapter()->delete(\Maho\Queue\QueueManager::tableName());
}

function makeEmailMessage(string $subject = 'Test subject'): Mage_Core_Model_Email_SendMessage
{
    return new Mage_Core_Model_Email_SendMessage(
        subject: $subject,
        body: '<p>Body</p>',
        isPlain: false,
        fromEmail: 'from@example.com',
        fromName: 'From',
        recipients: [['to@example.com', 'To', Mage_Core_Model_Email_Queue::EMAIL_TYPE_TO]],
    );
}

function fetchQueueRows(): array
{
    return queueAdapter()->fetchAll(
        queueAdapter()->select()->from(\Maho\Queue\QueueManager::tableName())->order('message_id ASC'),
    );
}

function agoUtc(int $seconds): string
{
    return gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $seconds);
}

/*
|--------------------------------------------------------------------------
| Display Currency Helpers
|--------------------------------------------------------------------------
|
| Shared by the display-currency regression tests (issue #1238) in
| tests/Backend/Integration/.
|
*/

/** Switch a store to EUR display currency in-memory and return the USD→EUR rate. */
function useEurDisplayCurrency(int $storeId = 1): float
{
    $store = Mage::app()->getStore($storeId);

    if ($store->getBaseCurrencyCode() !== 'USD') {
        test()->markTestSkipped('Test expects USD base currency on store ' . $storeId);
    }

    $store->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_ALLOW, 'USD,EUR');
    $store->setConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, 'EUR');
    foreach (['available_currency_codes', 'disallowed_base_currency_code_index', 'current_currency', 'default_currency', 'base_currency'] as $memo) {
        $store->unsetData($memo);
    }

    $rate = (float) $store->getBaseCurrency()->getRate('EUR');
    if ($rate <= 0 || $rate == 1.0) {
        test()->markTestSkipped('USD→EUR rate not available or trivially 1');
    }

    expect($store->getCurrentCurrencyCode())->toBe('EUR');

    return $rate;
}

/**
 * Merge extra queue config the way another module's config.xml would, run the
 * assertions, then restore the original config exactly (a snapshot, so merging
 * into an existing node does not delete it on the way out).
 *
 * @param callable():void $body
 */
function withQueueConfig(string $xml, callable $body): void
{
    $node = Mage::getConfig()->getNode('global/queue');
    $snapshot = new Maho\Simplexml\Element($node->asXML());
    $node->extend(new Maho\Simplexml\Element($xml), true);
    \Maho\Queue\QueueManager::reset();

    try {
        $body();
    } finally {
        foreach (array_keys((array) $node->children()) as $child) {
            unset($node->{$child});
        }
        $node->extend($snapshot, true);
        \Maho\Queue\QueueManager::reset();
    }
}
