<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->previousSession = $_SESSION ?? null;
    $_SESSION = [];
    $store = Mage::app()->getDefaultStoreView();
    Mage::app()->setCurrentStore($store);
    $store->setConfig('catalog/frontend/list_mode', 'grid-list');
    $store->setConfig('catalog/frontend/grid_per_page', '10');
    $store->setConfig('catalog/frontend/grid_per_page_values', '10,20');
    $store->setConfig('catalog/frontend/list_per_page', '10');
    $store->setConfig('catalog/frontend/list_per_page_values', '10,20');
});

afterEach(function (): void {
    if ($this->previousSession === null) {
        unset($_SESSION);
    } else {
        $_SESSION = $this->previousSession;
    }
});

function toolbarWithQuery(string $query): Mage_Catalog_Block_Product_List_Toolbar
{
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/category' . $query)));

    return Mage::app()->getLayout()->createBlock('catalog/product_list_toolbar')
        ->setAvailableOrders(['name' => 'Name', 'price' => 'Price'])
        ->setDefaultOrder('name')
        ->setDefaultDirection('asc');
}

/** @return list<mixed> */
function toolbarChoices(Mage_Catalog_Block_Product_List_Toolbar $toolbar): array
{
    return [$toolbar->getCurrentOrder(), $toolbar->getCurrentDirection(), $toolbar->getCurrentMode(), $toolbar->getLimit()];
}

it('clears saved choices for explicit defaults only when memorizing is enabled', function (bool $memorize): void {
    $toolbar = toolbarWithQuery('?order=name&dir=asc&mode=grid&limit=10');
    $session = Mage::getSingleton('catalog/session');
    $saved = ['sort_order' => 'price', 'sort_direction' => 'desc', 'display_mode' => 'list', 'limit_page' => '20'];
    $session->addData($saved);
    if (!$memorize) {
        $toolbar->disableParamsMemorizing();
    }

    expect(toolbarChoices($toolbar))->toBe(['name', 'asc', 'grid', '10'])
        ->and(array_intersect_key($session->getData(), $saved))->toBe($memorize ? [] : $saved);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

it('uses explicit choices without initializing the catalog session when memorizing is disabled', function (): void {
    $toolbar = toolbarWithQuery('?order=price&dir=desc&mode=list&limit=20')->disableParamsMemorizing();

    expect(toolbarChoices($toolbar))->toBe(['price', 'desc', 'list', '20'])
        ->and(Mage::registry('_singleton/catalog/session'))->toBeNull();
});

it('can read saved choices when no explicit preferences are present', function (): void {
    $toolbar = toolbarWithQuery('')->disableParamsMemorizing();
    Mage::getSingleton('catalog/session')->addData([
        'sort_order' => 'price', 'sort_direction' => 'desc', 'display_mode' => 'list', 'limit_page' => '20',
    ]);

    expect(toolbarChoices($toolbar))->toBe(['price', 'desc', 'list', '20'])
        ->and(Mage::registry(Mage_Core_Model_Session_Abstract::REGISTRY_KEY))->toBeNull();
});
