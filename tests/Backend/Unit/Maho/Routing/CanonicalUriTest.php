<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

function canonicalUriConfig(string $trailingSlash = 'leave'): void
{
    $config = Mage::getConfig();
    // `checkBaseUrl` runs first and would redirect on the host mismatch with the installed base URL.
    $config->saveConfig('web/url/redirect_to_base', '0');
    $config->saveConfig('web/url/trailing_slash_behavior', $trailingSlash);
    Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
    $config->reinit();
    Mage::app()->reinitStores();
}

function canonicalUriResetConfig(): void
{
    $config = Mage::getConfig();
    $config->deleteConfig('web/url/redirect_to_base');
    $config->deleteConfig('web/url/trailing_slash_behavior');
    Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
    $config->reinit();
    Mage::app()->reinitStores();
}

function canonicalUriDispatch(string $uri, string $script = '/index.php', string $method = 'GET', array $postData = []): Mage_Core_Controller_Response_Http
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
    $query = [];
    $queryPos = strpos($uri, '?');
    if ($queryPos !== false) {
        parse_str(substr($uri, $queryPos + 1), $query);
    }
    $symfonyRequest = new SymfonyRequest($query, $postData, [], [], [], $server);

    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    // The URL-rewrite lookup runs after this step and would redirect on its own.
    $request->isStraight(true);

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
    foreach ($response->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Location') === 0) {
            return $header['value'];
        }
    }
    return null;
}

describe('Front observer canonical URI', function () {
    beforeEach(function () {
        canonicalUriConfig();
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

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
