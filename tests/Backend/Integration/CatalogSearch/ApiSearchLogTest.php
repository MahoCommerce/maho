<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GetCollection;
use Mage\Catalog\Api\ProductProvider;
use Maho\ApiPlatform\Service\StoreContext;

uses(Tests\MahoBackendTestCase::class);

/**
 * The storefront search and the product search of the API count a term in the Search Terms report through
 * Mage_CatalogSearch_Service_Query (#1348), with the same minimum length and the same budget.
 */

const API_SEARCH_LOG_TOKEN = 'zzapisearchlog';

function apiSearchLogCleanup(): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
        Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query'),
        ['query_text LIKE ?' => API_SEARCH_LOG_TOKEN . '%'],
    );
}

function apiSearchLogRow(string $text): ?Mage_CatalogSearch_Model_Query
{
    /** @var Mage_CatalogSearch_Model_Query $query */
    $query = Mage::getModel('catalogsearch/query');
    $query->setStoreId(1);
    $query->loadByQuery($text);
    return $query->getId() ? $query : null;
}

function apiSearch(string $text): void
{
    iterator_to_array(new ProductProvider(null)->provide(new GetCollection(), [], ['filters' => ['search' => $text]]));
}

beforeEach(function (): void {
    // A fresh client per test, so each test starts with a full budget
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    StoreContext::setStore(1);
    apiSearchLogCleanup();
    $this->config = [
        Mage_CatalogSearch_Model_Query::XML_PATH_MIN_QUERY_LENGTH => Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MIN_QUERY_LENGTH),
        Mage_CatalogSearch_Model_Query::XML_PATH_LOG_RATE_LIMIT => Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_RATE_LIMIT),
    ];
});

afterEach(function (): void {
    foreach ($this->config as $path => $value) {
        Mage::app()->getStore()->setConfig($path, $value);
    }
    apiSearchLogCleanup();
    Mage::unregister('_helper/catalogsearch');
    new StoreContext()->reset();
});

it('counts each API search of a term, with its number of results', function (): void {
    $text = API_SEARCH_LOG_TOKEN . uniqid();

    apiSearch($text);
    expect(apiSearchLogRow($text)?->getPopularity())->toBe(1)
        ->and(apiSearchLogRow($text)?->getNumResults())->toBe(0);

    apiSearch($text);
    expect(apiSearchLogRow($text)?->getPopularity())->toBe(2);
});

it('does not count a text shorter than the minimum length', function (): void {
    Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MIN_QUERY_LENGTH, 100);
    $text = API_SEARCH_LOG_TOKEN . uniqid();

    apiSearch($text);

    expect(apiSearchLogRow($text))->toBeNull();
});

it('does not count a search over the budget of the client', function (): void {
    Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_RATE_LIMIT, 1);
    $first = API_SEARCH_LOG_TOKEN . uniqid();
    $second = API_SEARCH_LOG_TOKEN . uniqid();

    apiSearch($first);
    apiSearch($second);

    expect(apiSearchLogRow($first))->not->toBeNull()
        ->and(apiSearchLogRow($second))->toBeNull();
});
