<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

function storeCodeRootDispatch(string $uri, string $method = 'GET'): Mage_Core_Controller_Response_Http
{
    $server = [
        'SCRIPT_NAME' => '/index.php',
        'SCRIPT_FILENAME' => '/index.php',
        'PHP_SELF' => '/index.php',
        'REQUEST_URI' => $uri,
        'REQUEST_METHOD' => $method,
        'HTTP_HOST' => 'localhost',
    ];
    $request = new Mage_Core_Controller_Request_Http(new SymfonyRequest([], [], [], [], [], $server));
    $request->isStraight(true);
    $response = new Mage_Core_Controller_Response_Http();
    Mage::app()->setRequest($request);
    Mage::app()->setResponse($response);
    $request->setPathInfo();

    $front = new Mage_Core_Controller_Varien_Front();
    $event = new \Maho\Event\Observer();
    $event->setData('front', $front);
    (new Mage_Core_Model_Controller_Front_Observer())->onDispatchBefore($event);
    return $response;
}

describe('Front observer store code root redirect', function () {
    beforeEach(function (): void {
        Mage::getConfig()->saveConfig('web/url/redirect_to_base', '0');
        Mage::getConfig()->saveConfig('web/url/trailing_slash_behavior', 'leave');
        Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        Mage::app()->getStore()->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '1');
    });

    afterEach(function (): void {
        Mage::app()->getStore()->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
        Mage::getConfig()->deleteConfig('web/url/redirect_to_base');
        Mage::getConfig()->deleteConfig('web/url/trailing_slash_behavior');
        Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

    it('sends the bare root to the home page of the current store, keeping the query', function () {
        $store = Mage::app()->getStore();
        $response = storeCodeRootDispatch('/?utm_source=newsletter');

        expect($response->isRedirect())->toBeTrue();
        expect($response->getSymfonyResponse()->headers->get('Location'))
            ->toBe($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . '?utm_source=newsletter')
            ->toContain('/' . $store->getCode() . '/');
    });

    it('leaves the prefixed root, deeper paths and posts alone', function () {
        $code = Mage::app()->getStore()->getCode();
        expect(storeCodeRootDispatch('/' . $code . '/')->isRedirect())->toBeFalse();
        expect(storeCodeRootDispatch('/customer/account/login/')->isRedirect())->toBeFalse();
        expect(storeCodeRootDispatch('/', 'POST')->isRedirect())->toBeFalse();
    });

    it('does nothing when store codes are not in URLs', function () {
        Mage::app()->getStore()->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
        expect(storeCodeRootDispatch('/')->isRedirect())->toBeFalse();
    });
});
