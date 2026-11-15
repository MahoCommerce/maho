<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Adminhtml\Api\GlobalSearchProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The search field in the header of the admin and GET /global-search search through
 * Mage_Adminhtml_Service_GlobalSearch (#1348). Each test does the same search through both transports and compares the outcome.
 */

/**
 * Post $params to the global search action of the admin, as the search field in the header does.
 *
 * @param array<string, string> $params
 * @return list<array{id: string, name: string, description: string}> the items of the result list
 */
function globalSearchViaAdmin(array $params): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/admin/index/globalSearch', 'POST', $params),
    );
    $request->setRouteName('adminhtml')->setControllerName('index')->setActionName('globalSearch')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Adminhtml_IndexController($request, $response)->globalSearchAction();

    preg_match_all(
        '#<li id="([^"]*)"[^>]*>.*?<strong>(.*?)</strong>\s*<span class="informal">(.*?)</span>#s',
        (string) $response->getBody(),
        $matches,
        PREG_SET_ORDER,
    );
    return array_map(static fn(array $match): array => [
        'id' => $match[1],
        'name' => html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5),
        'description' => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5),
    ], $matches);
}

/**
 * Run GET /global-search with $filters for an admin token.
 *
 * @param array<string, string> $filters
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function globalSearchViaApi(array $filters): array
{
    $request = SymfonyRequest::create('/api/rest/v2/global-search', 'GET', $filters, server: ['HTTP_AUTHORIZATION' => 'Bearer pest']);
    $outcome = AdminApiParity::api($request, fn(): mixed => new GlobalSearchProvider(AdminApiParity::adminSecurity())
        ->provide(new Get(), [], ['filters' => $filters, 'request' => $request]));
    if ($outcome['result'] instanceof Symfony\Component\HttpFoundation\JsonResponse) {
        $outcome['result'] = json_decode((string) $outcome['result']->getContent(), true);
    }
    return $outcome;
}

/**
 * @param list<array{id: string, name: string, description: string}> $items
 * @return list<int> the IDs of the customers in the result list of the admin
 */
function globalSearchAdminCustomerIds(array $items): array
{
    $ids = [];
    foreach ($items as $item) {
        if (preg_match('#^customer/1/(\d+)$#', $item['id'], $match)) {
            $ids[] = (int) $match[1];
        }
    }
    return $ids;
}

/**
 * @param array{result: mixed} $outcome
 * @return list<int> the IDs of the customers in the response of the API
 */
function globalSearchApiCustomerIds(array $outcome): array
{
    $items = array_filter($outcome['result']['items'] ?? [], static fn(array $item): bool => $item['entity'] === 'customer');
    return array_values(array_map(static fn(array $item): int => $item['entityId'], $items));
}

function globalSearchCreateCustomer(string $lastname): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1)
        ->setStoreId(1)
        ->setFirstname('Pest')
        ->setLastname($lastname)
        ->setEmail(uniqid('pest-gsearch-') . '@example.test')
        ->save();
    return $customer;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML)->setPackageName('default')->setTheme('default');
    Mage::app()->getLayout()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);
    Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)->setConfig(Mage_Adminhtml_Service_GlobalSearch::XML_PATH_ENABLE, '1');

    $this->lastname = 'Gsearch' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10);
    $this->customers = [globalSearchCreateCustomer($this->lastname), globalSearchCreateCustomer($this->lastname)];
    $this->user = AdminRoleFixture::login(['admin/global_search', 'admin/customer']);
});

afterEach(function () {
    AdminRoleFixture::delete($this->user);
    foreach ($this->customers as $customer) {
        $customer->delete();
    }
});

it('finds the same customers on both transports', function () {
    $expected = array_map(static fn(Mage_Customer_Model_Customer $customer): int => (int) $customer->getId(), $this->customers);
    sort($expected);

    $admin = globalSearchAdminCustomerIds(globalSearchViaAdmin(['query' => $this->lastname]));
    $api = globalSearchApiCustomerIds(globalSearchViaApi(['query' => $this->lastname]));
    sort($admin);
    sort($api);

    expect($admin)->toBe($expected)
        ->and($api)->toBe($expected);
});

it('applies the limit of each source on both transports', function () {
    $admin = globalSearchViaAdmin(['query' => $this->lastname, 'limit' => '1']);
    $api = globalSearchViaApi(['query' => $this->lastname, 'limit' => '1']);

    expect(globalSearchAdminCustomerIds($admin))->toHaveCount(1)
        ->and(globalSearchApiCustomerIds($api))->toHaveCount(1);
});

it('gives no results for a query that is too short on both transports', function (string $query) {
    expect(globalSearchViaAdmin(['query' => $query]))->toBe([])
        ->and(globalSearchViaApi(['query' => $query])['result'])->toBe(['query' => trim($query), 'items' => []]);
})->with(['empty' => [''], 'one character' => ['G'], 'one character with spaces' => [' G ']]);

it('searches only the sources that the admin role allows on both transports', function () {
    AdminRoleFixture::delete($this->user);
    $this->user = AdminRoleFixture::login(['admin/global_search', 'admin/catalog']);

    expect(globalSearchAdminCustomerIds(globalSearchViaAdmin(['query' => $this->lastname])))->toBe([])
        ->and(globalSearchApiCustomerIds(globalSearchViaApi(['query' => $this->lastname])))->toBe([]);
});

it('refuses the search with the same message on both transports when it is disabled', function () {
    Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)->setConfig(Mage_Adminhtml_Service_GlobalSearch::XML_PATH_ENABLE, '0');

    $admin = globalSearchViaAdmin(['query' => $this->lastname]);
    $api = globalSearchViaApi(['query' => $this->lastname]);

    expect(Mage::getService('adminhtml/globalSearch')->isEnabled())->toBeFalse()
        ->and($api['status'])->toBe(403)
        ->and($api['errors'][0])->toContain('The global search is disabled')
        ->and($admin)->toBe([['id' => 'error', 'name' => 'Access Denied', 'description' => $api['errors'][0]]]);
});
