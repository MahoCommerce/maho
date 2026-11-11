<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GetCollection;
use Mage\Catalog\Api\ProductProvider;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The search result page of the storefront and the product search of the API prepare and count a search through
 * Mage_CatalogSearch_Service_Query (#1348). Each test gives the same text to both transports and compares the
 * Search Terms report.
 */

const SEARCH_PARITY_TOKEN = 'zzsearchparity';

function searchParityCleanup(): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
        Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query'),
        ['query_text LIKE ?' => SEARCH_PARITY_TOKEN . '%'],
    );
}

/**
 * Add the search term $text with a redirect, so that the result page of the storefront answers without a layout.
 */
function searchParityTerm(string $text, int $storeId): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->insert(
        Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query'),
        [
            'query_text' => $text,
            'num_results' => 0,
            'popularity' => 5,
            'redirect' => 'http://localhost/pest-search-parity',
            'store_id' => $storeId,
            'is_active' => 1,
            'is_processed' => 1,
            'updated_at' => Mage_Core_Model_Locale::nowUtc(),
        ],
    );
}

function searchParityPopularity(string $text, int $storeId): ?int
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $popularity = $adapter->fetchOne(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query'), ['popularity'])
            ->where('query_text = ?', $text)
            ->where('store_id = ?', $storeId),
    );
    return $popularity === false ? null : (int) $popularity;
}

function searchParityViaStorefront(string $text): void
{
    // A new helper for a new request
    Mage::unregister('_helper/catalogsearch');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/catalogsearch/result/', 'GET', ['q' => $text]));
    $request->setPathInfo('/catalogsearch/result/');
    $request->setRouteName('catalogsearch');
    $request->setControllerName('result');
    $request->setActionName('index');
    $request->setControllerModule('Mage_CatalogSearch');
    $request->setDispatched(true);
    Mage::app()->setRequest($request);

    new Mage_CatalogSearch_ResultController($request, new Mage_Core_Controller_Response_Http())->indexAction();
}

function searchParityViaApi(string $text): void
{
    // The API request has no search text of its own
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/api/rest/v2/products', 'GET')));
    iterator_to_array(new ProductProvider(null)->provide(new GetCollection(), [], ['filters' => ['search' => $text]]));
}

beforeEach(function (): void {
    // A fresh client per test, so each test starts with a full budget
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    $this->storeId = (int) Mage::app()->getStore()->getId();
    $this->request = Mage::app()->getRequest();
    StoreContext::setStore($this->storeId);
    searchParityCleanup();
});

afterEach(function (): void {
    searchParityCleanup();
    Mage::unregister('_helper/catalogsearch');
    Mage::app()->setRequest($this->request);
    new StoreContext()->reset();
});

it('counts a search of the same term once on each transport', function (): void {
    $text = SEARCH_PARITY_TOKEN . uniqid();
    searchParityTerm($text, $this->storeId);

    searchParityViaStorefront($text);
    $afterStorefront = searchParityPopularity($text, $this->storeId);
    searchParityViaApi($text);
    $afterApi = searchParityPopularity($text, $this->storeId);

    expect($afterStorefront)->toBe(6)
        ->and($afterApi)->toBe(7);
});

it('cuts a text that is too long at the same length on both transports', function (): void {
    $maxLength = (int) Mage::helper('catalogsearch')->getMaxQueryLength();
    $term = substr(SEARCH_PARITY_TOKEN . str_repeat('a', $maxLength), 0, $maxLength);
    searchParityTerm($term, $this->storeId);

    searchParityViaStorefront($term . 'tail');
    $afterStorefront = searchParityPopularity($term, $this->storeId);
    searchParityViaApi($term . 'tail');
    $afterApi = searchParityPopularity($term, $this->storeId);

    expect($afterStorefront)->toBe(6)
        ->and($afterApi)->toBe(7)
        ->and(searchParityPopularity($term . 'tail', $this->storeId))->toBeNull();
});
