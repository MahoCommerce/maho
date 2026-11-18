<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Original base URL captured before this file mutates it, so the reset restores the
 * real installed value (persisted config leaks across the shared test DB).
 */
function canonicalUriOriginalBaseUrl(): string
{
    static $orig = null;
    $orig ??= (string) Mage::getStoreConfig('web/unsecure/base_url');
    return $orig;
}

/** The installed store code flag, restored after each case: the sample data turns it on. */
function canonicalUriOriginalStoreInUrl(): string
{
    static $orig = null;
    $orig ??= (string) Mage::getStoreConfig('web/url/use_store');
    return $orig;
}

function canonicalUriConfig(string $trailingSlash = 'leave', ?string $baseUrl = null): void
{
    canonicalUriOriginalBaseUrl();
    canonicalUriOriginalStoreInUrl();
    $config = Mage::getConfig();
    // `checkBaseUrl` runs first and would redirect on the host mismatch with the installed base URL.
    $config->saveConfig('web/url/redirect_to_base', '0');
    // `checkStoreCodeInUrl` would redirect the bare root to the store home when the store code belongs in the URL.
    $config->saveConfig('web/url/use_store', '0');
    $config->saveConfig('web/url/trailing_slash_behavior', $trailingSlash);
    if ($baseUrl !== null) {
        $config->saveConfig('web/unsecure/base_url', $baseUrl);
    }
    Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
    $config->reinit();
    Mage::app()->reinitStores();
    // reinitStores() creates new store objects. The current store still points to the old
    // object, and the config cache of that object holds the old base URL.
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
}

function canonicalUriResetConfig(): void
{
    $config = Mage::getConfig();
    $config->deleteConfig('web/url/redirect_to_base');
    $config->deleteConfig('web/url/trailing_slash_behavior');
    $config->saveConfig('web/unsecure/base_url', canonicalUriOriginalBaseUrl());
    $config->saveConfig('web/url/use_store', canonicalUriOriginalStoreInUrl());
    Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
    $config->reinit();
    Mage::app()->reinitStores();
}

function canonicalUriDispatch(string $uri, string $script = '/index.php', string $method = 'GET', array $postData = [], bool $straight = true): Mage_Core_Controller_Response_Http
{
    // `SymfonyRequest::create()` validates the URI, so it cannot express what a web server hands over.
    $server = [
        'SCRIPT_NAME' => $script,
        'SCRIPT_FILENAME' => $script,
        'PHP_SELF' => $script,
        'REQUEST_URI' => $uri,
        'REQUEST_METHOD' => $method,
        'HTTP_HOST' => 'localhost',
    ];
    // The observer only reads REQUEST_URI; the query bag is never consulted on this path.
    $symfonyRequest = new SymfonyRequest([], $postData, [], [], [], $server);

    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    // The URL-rewrite lookup runs after this step and would redirect on its own.
    $request->isStraight($straight);

    $response = new Mage_Core_Controller_Response_Http();

    Mage::app()->setRequest($request);
    Mage::app()->setResponse($response);

    $front = new Mage_Core_Controller_Varien_Front();
    $event = new \Maho\Event\Observer();
    $event->setData('front', $front);

    (new Mage_Core_Model_Controller_Front_Observer())->onDispatchBefore($event);

    return $response;
}

function canonicalUriLocation(Mage_Core_Controller_Response_Http $response): ?string
{
    return $response->getSymfonyResponse()->headers->get('Location');
}

describe('Front observer canonical URI', function () {
    beforeEach(fn() => canonicalUriConfig());

    afterEach(fn() => canonicalUriResetConfig());

    it('redirects the bare front controller to the storefront root', function () {
        $response = canonicalUriDispatch('/index.php');

        expect($response->isRedirect())->toBeTrue();
        expect(canonicalUriLocation($response))->toBe('/');
    });

    it('keeps the query string when redirecting the bare front controller', function () {
        $response = canonicalUriDispatch('/index.php?utm_source=newsletter');

        expect(canonicalUriLocation($response))->toBe('/?utm_source=newsletter');
    });

    it('redirects a path served through the front controller', function () {
        $response = canonicalUriDispatch('/index.php/catalog/category/view/id/3');

        expect(canonicalUriLocation($response))->toBe('/catalog/category/view/id/3');
    });

    it('keeps the subdirectory prefix of a store installed below the document root', function () {
        $response = canonicalUriDispatch('/shop/index.php/customer/account/login', '/shop/index.php');

        expect(canonicalUriLocation($response))->toBe('/shop/customer/account/login');
    });

    it('leaves a path that only starts with the front controller name alone', function () {
        $response = canonicalUriDispatch('/index.php.bak');

        expect($response->isRedirect())->toBeFalse();
    });

    it('leaves a legacy API request alone, since SOAP clients may not follow redirects', function () {
        $response = canonicalUriDispatch('/index.php/api/soap?wsdl=1');

        expect($response->isRedirect())->toBeFalse();
    });

    it('exempts a legacy API request even with a doubled slash', function () {
        $response = canonicalUriDispatch('//api/soap?wsdl=1');

        expect($response->isRedirect())->toBeFalse();
    });

    it('strips a percent-encoded front controller script', function () {
        $response = canonicalUriDispatch('/%69ndex.php/catalog/category/view/id/3');

        expect(canonicalUriLocation($response))->toBe('/catalog/category/view/id/3');
    });

    it('leaves a path parse_url cannot parse intact', function () {
        canonicalUriConfig('add');

        $response = canonicalUriDispatch('/product/sku:123/');

        expect($response->isRedirect())->toBeFalse();
    });

    it('keeps the trailing slash on the root of a store installed below the document root', function () {
        canonicalUriConfig('remove', 'http://localhost/shop/');

        $response = canonicalUriDispatch('/shop/', '/shop/index.php');

        expect($response->isRedirect())->toBeFalse();
    });

    it('redirects the slashless subdirectory root to the storefront root', function () {
        canonicalUriConfig('remove', 'http://localhost/shop/');

        $response = canonicalUriDispatch('/shop', '/shop/index.php');

        expect(canonicalUriLocation($response))->toBe('/shop/');
    });

    it('leaves a rewritten URL alone', function () {
        $response = canonicalUriDispatch('/catalog/category/view/id/3');

        expect($response->isRedirect())->toBeFalse();
    });

    it('leaves a query string holding a URL untouched', function () {
        $response = canonicalUriDispatch('/catalog/category/view/id/3?back=https://example.com/x');

        expect($response->isRedirect())->toBeFalse();
    });

    it('never sends a Location the browser reads as another origin', function () {
        canonicalUriConfig('add');

        $response = canonicalUriDispatch('/\\example.com/x');

        expect(canonicalUriLocation($response))->toBe('/example.com/x/');
    });

    it('does not redirect a POST, which would lose the submitted body', function () {
        $response = canonicalUriDispatch('/index.php', '/index.php', 'POST', ['form_key' => 'x']);

        expect($response->isRedirect())->toBeFalse();
    });
});

describe('Front observer canonical URI with a URL rewrite', function () {
    beforeEach(fn() => canonicalUriConfig('add'));

    afterEach(function () {
        Mage::getSingleton('core/resource')->getConnection('core_write')
            ->delete(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), ['id_path LIKE ?' => 'pest_canonical_%']);
        unset($_GET['___from_store']);
        canonicalUriResetConfig();
    });

    it('adds the slash when ___from_store is an array and no rewrite matches', function () {
        $_GET['___from_store'] = ['default'];

        $response = canonicalUriDispatch('/pest-no-rewrite', straight: false);

        expect($response->getHttpResponseCode())->toBe(301)
            ->and(canonicalUriLocation($response))->toBe('/pest-no-rewrite/');
    });

    it('goes to the target of a redirect rewrite with one redirect, or adds the slash to any other rewrite', function (string $options, string $target, string $uri, int $code, string $location) {
        Mage::getSingleton('core/resource')->getConnection('core_write')->insert(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), [
            'store_id' => (int) Mage::app()->getStore()->getId(),
            'id_path' => 'pest_canonical_' . uniqid(),
            'request_path' => 'pest-old-brand/',
            'target_path' => $target,
            'is_system' => 0,
            'options' => $options,
        ]);

        $response = canonicalUriDispatch($uri, straight: false);

        expect($response->getHttpResponseCode())->toBe($code)
            ->and(canonicalUriLocation($response))->toBe($location);
    })->with([
        'permanent redirect' => ['RP', 'pest-new-brand/', '/pest-old-brand', 301, '/pest-new-brand/'],
        'temporary redirect' => ['R', 'pest-new-brand/', '/pest-old-brand', 302, '/pest-new-brand/'],
        'external target' => ['', 'https://example.com/new-brand/', '/pest-old-brand', 302, 'https://example.com/new-brand/'],
        'no redirect' => ['', 'catalog/category/view/id/3', '/pest-old-brand', 301, '/pest-old-brand/'],
        'script name in the path' => ['RP', 'pest-new-brand/', '/index.php/pest-old-brand', 301, '/pest-old-brand/'],
    ]);
});
