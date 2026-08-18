<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * `Mage_Core_Model_Controller_Front_Observer::checkCanonicalUri()` sends the front
 * controller's own path back to the storefront root. Maho never generates a URL
 * holding `index.php`, but every web server keeps `/index.php` reachable, which
 * serves the whole catalog a second time under duplicate URLs.
 *
 * The script segment is detected through the request base URL, the same value the
 * router already strips to build the path info, so a store installed in a
 * subdirectory keeps its prefix.
 */
function canonicalUriConfig(): void
{
    $config = Mage::getConfig();
    // `checkBaseUrl` runs before the canonical-URI step and would redirect first
    // on the host mismatch between the test URI and the installed base URL.
    $config->saveConfig('web/url/redirect_to_base', '0');
    $config->saveConfig('web/url/trailing_slash_behavior', 'leave');
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
    $server = ['SCRIPT_NAME' => $script, 'SCRIPT_FILENAME' => $script, 'PHP_SELF' => $script];
    $symfonyRequest = SymfonyRequest::create('http://localhost' . $uri, $method, $postData, [], [], $server);

    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    // The URL-rewrite lookup runs after this step and would redirect on its own
    // when no canonical redirect is expected.
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

    it('leaves a rewritten URL alone', function () {
        $response = canonicalUriDispatch('/catalog/category/view/id/3');

        expect($response->isRedirect())->toBeFalse();
    });

    it('does not redirect a POST, which would lose the submitted body', function () {
        $response = canonicalUriDispatch('/index.php', '/index.php', 'POST', ['form_key' => 'x']);

        expect($response->isRedirect())->toBeFalse();
    });
});
