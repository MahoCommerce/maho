<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for setPathInfo()'s store-code prefix stripping.
 *
 * When web/url/use_store=1, both _pathInfo AND _requestString must end up
 * with the prefix stripped. They're computed in setPathInfo() and consumed
 * separately — _pathInfo by the routing layer, _requestString by
 * Mage_Core_Model_Store::getCurrentUrl() when building cross-store links.
 *
 * Regression: an earlier iteration of the routing migration extracted the
 * stripping into the dispatch_before observer and called $request->setPathInfo($newPath)
 * to update only _pathInfo. _requestString retained the old prefix, causing the
 * store switcher to generate URLs like /fr/en/?___from_store=en — the FR base
 * URL concatenated with the EN-prefixed _requestString.
 */

function makeRequestForPath(string $requestUri): Mage_Core_Controller_Request_Http
{
    $symfonyRequest = SymfonyRequest::create($requestUri, 'GET', server: [
        'REQUEST_URI' => $requestUri,
        'HTTP_HOST' => 'maho.test',
    ]);
    return new Mage_Core_Controller_Request_Http($symfonyRequest);
}

function setUseStoreFlag(string $value): void
{
    // Pre-populate each store's $_configCache so that getConfigFlag('web/url/use_store')
    // returns our value without consulting the merged XML config tree at all. This
    // sidesteps cross-DB / merge-timing differences (an earlier approach using
    // setNode + cache clear worked on MySQL but failed on PostgreSQL CI).
    $stores = Mage::app()->getStores(true, true);
    $configCacheRef = new ReflectionProperty(Mage_Core_Model_Store::class, '_configCache');
    foreach ($stores as $store) {
        $cache = $configCacheRef->getValue($store) ?? [];
        $cache['web/url/use_store'] = $value;
        $configCacheRef->setValue($store, $cache);
    }
}

describe('setPathInfo() with web/url/use_store=1', function () {
    beforeEach(function () {
        setUseStoreFlag('1');
    });

    afterEach(function () {
        setUseStoreFlag('0');
    });

    it('strips a known store code from both pathInfo and requestString', function () {
        $req = makeRequestForPath('/en/customer/account/');
        $req->setPathInfo();

        expect($req->getPathInfo())->toBe('/customer/account/');
        expect($req->getRequestString())->toBe('/customer/account/');
        expect($req->getOriginalPathInfo())->toBe('/customer/account/');
    });

    it('strips the prefix at root, leaving "/"', function () {
        $req = makeRequestForPath('/en/');
        $req->setPathInfo();

        expect($req->getPathInfo())->toBe('/');
        expect($req->getRequestString())->toBe('/');
    });

    it('activates the matched store as the current store', function () {
        $req = makeRequestForPath('/fr/');
        $req->setPathInfo();

        expect(Mage::app()->getStore()->getCode())->toBe('fr');
    });

    it('does not modify pathInfo for an unknown store code, and sets noRoute', function () {
        $req = makeRequestForPath('/badcode/foo/bar');
        $req->setPathInfo();

        // Path is left intact; routing layer will produce a 404 via the action name
        expect($req->getPathInfo())->toBe('/badcode/foo/bar');
        expect($req->getRequestString())->toBe('/badcode/foo/bar');
        expect($req->getActionName())->toBe('noRoute');
    });

    it('leaves the path untouched at the bare root /', function () {
        $req = makeRequestForPath('/');
        $req->setPathInfo();

        expect($req->getPathInfo())->toBe('/');
        expect($req->getRequestString())->toBe('/');
    });
});

describe('setPathInfo() with web/url/use_store=0', function () {
    beforeEach(function () {
        setUseStoreFlag('0');
    });

    it('does not strip store-code-like first segments when the flag is off', function () {
        $req = makeRequestForPath('/en/customer/account/');
        $req->setPathInfo();

        expect($req->getPathInfo())->toBe('/en/customer/account/');
        expect($req->getRequestString())->toBe('/en/customer/account/');
    });
});
