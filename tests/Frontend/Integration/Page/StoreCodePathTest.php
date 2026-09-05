<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function storeCodeRequest(string $uri): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http();
    $request->setRequestUri($uri);
    Mage::app()->setRequest($request);
    return $request;
}

beforeEach(fn() => Mage::app()->getStore()->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '1'));
afterEach(function (): void {
    Mage::app()->getStore()->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView()->getCode());
});

it('strips a known store code from the path and selects that store', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    $request = storeCodeRequest('/' . $store->getCode() . '/catalog/category/view');

    expect($request->setPathInfo()->getOriginalPathInfo())->toBe('/catalog/category/view');
    expect(Mage::app()->getStore()->getCode())->toBe($store->getCode());
});

it('never treats the admin store code as a storefront store, even with a custom admin path', function (): void {
    $property = new ReflectionProperty(\Maho\Routing\RouteCollectionBuilder::class, 'adminFrontName');
    $previous = $property->getValue();
    $property->setValue(null, 'backend');
    try {
        $request = storeCodeRequest('/admin/dashboard');
        $request->setPathInfo();
    } finally {
        $property->setValue(null, $previous);
    }

    expect($request->getOriginalPathInfo())->toBe('/admin/dashboard');
    expect($request->getActionName())->toBe('noRoute');
    expect(Mage::app()->getStore()->getCode())->not->toBe(Mage_Core_Model_Store::ADMIN_CODE);
});
