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
 * The HTTPS-enforcement branch of `Mage_Core_Model_Controller_Front_Observer`
 * moved here from `Mage_Core_Controller_Varien_Front::_checkBaseUrl()` in the
 * Symfony-routing migration. Integration-test the observer through its public
 * `onDispatchBefore` entry point — the same call site the front controller
 * uses at runtime — with config persisted via `saveConfig()` so the merged
 * config tree reflects a real deployment, not a patched XML node.
 */

function httpsConfig(string $unsecure, string $secure, string $useInAdmin, string $useInFrontend): void
{
    $config = Mage::getConfig();
    $config->saveConfig('web/unsecure/base_url', $unsecure);
    $config->saveConfig('web/secure/base_url', $secure);
    $config->saveConfig('web/secure/use_in_adminhtml', $useInAdmin);
    $config->saveConfig('web/secure/use_in_frontend', $useInFrontend);
    // Disable base-URL auto-redirect so `checkBaseUrl` doesn't fire before
    // `enforceHttps`. We're testing the HTTPS step, not base-URL redirection.
    $config->saveConfig('web/url/redirect_to_base', '0');
    // Likewise `checkTrailingSlash` would 301-redirect to add/remove the
    // trailing slash before `enforceHttps` runs. Disable here.
    $config->saveConfig('web/url/trailing_slash_behavior', 'leave');
    // Drop the `store_global_config_cache` entry so fresh store instances
    // don't re-seed their per-instance config cache from stale values.
    Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
    $config->reinit();
    Mage::app()->reinitStores();
    // `enforceHttps` consults `getUseSessionInUrl()` and, on a redirect, will
    // try to call `core/url::getRedirectUrl`, which attempts to start a
    // session. In Pest the session is already active from bootstrap, so the
    // second start throws `LogicException: Cannot change the name of an active
    // session`. Disable so the observer skips the session-URL branch.
    Mage::app()->setUseSessionInUrl(false);
}

function httpsResetConfig(): void
{
    $config = Mage::getConfig();
    $config->saveConfig('web/unsecure/base_url', 'http://maho.test/');
    $config->saveConfig('web/secure/base_url', 'http://maho.test/');
    $config->saveConfig('web/secure/use_in_adminhtml', '0');
    $config->saveConfig('web/secure/use_in_frontend', '0');
    $config->deleteConfig('web/url/redirect_to_base');
    $config->deleteConfig('web/url/trailing_slash_behavior');
    Mage::app()->cleanCache([Mage_Core_Model_Config::CACHE_TAG]);
    $config->reinit();
    Mage::app()->reinitStores();
}

function dispatchBefore(string $pathInfo, string $method = 'GET', ?string $scheme = null, array $postData = []): Mage_Core_Controller_Response_Http
{
    $uri = ($scheme ?? 'http') . '://localhost' . $pathInfo;
    $symfonyRequest = SymfonyRequest::create($uri, $method, $postData);
    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    $request->setPathInfo($pathInfo);
    // `rewriteDb` step consults the URL-rewrite table and can redirect before
    // `enforceHttps` ever runs. `isStraight(true)` skips it — our target here
    // is the HTTPS branch, not URL rewriting.
    $request->isStraight(true);

    $response = new Mage_Core_Controller_Response_Http();

    Mage::app()->setRequest($request);
    Mage::app()->setResponse($response);

    $front = new Mage_Core_Controller_Varien_Front();
    $event = new \Maho\Event\Observer();
    $event->setData('front', $front);

    $observer = new Mage_Core_Model_Controller_Front_Observer();
    $observer->onDispatchBefore($event);

    return $response;
}

function locationHeader(Mage_Core_Controller_Response_Http $response): ?string
{
    foreach ($response->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Location') === 0) {
            return $header['value'];
        }
    }
    return null;
}

describe('Front observer HTTPS enforcement — skip conditions', function () {
    beforeEach(function () {
        httpsConfig('http://localhost/', 'https://localhost/', '1', '1');
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

    afterEach(fn() => httpsResetConfig());

    it('does not redirect on a POST submission even when HTTPS is required', function () {
        // The skip check is `$request->getPost()` — body data, not method —
        // specifically to preserve form posts that would otherwise lose data
        // on a 302 redirect. Mage's getPost() reads from `$_POST`, so seed it
        // directly rather than via the SymfonyRequest constructor.
        $_POST = ['login' => ['username' => 'x']];
        try {
            $response = dispatchBefore('/customer/account/login', 'POST');
            expect($response->isRedirect())->toBeFalse();
        } finally {
            $_POST = [];
        }
    });

    it('does not redirect when the request is already HTTPS', function () {
        $response = dispatchBefore('/customer/account/login', 'GET', 'https');

        expect($response->isRedirect())->toBeFalse();
    });
});

describe('Front observer HTTPS enforcement — admin branch', function () {
    afterEach(function () {
        httpsResetConfig();
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

    it('does not redirect when neither base URL is HTTPS', function () {
        httpsConfig('http://localhost/', 'http://localhost/', '0', '0');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $response = dispatchBefore('/admin/dashboard');

        expect($response->isRedirect())->toBeFalse();
    });

    it('redirects to HTTPS when unsecure base URL is already HTTPS (scheme consistency)', function () {
        // Belt-and-braces config: operator has set unsecure_base_url to
        // https://... — the request must still be redirected rather than
        // served over HTTP.
        httpsConfig('https://localhost/', 'https://localhost/', '0', '0');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $response = dispatchBefore('/admin/dashboard');

        expect($response->isRedirect())->toBeTrue();
        expect(locationHeader($response))->toStartWith('https://');
    });

    it('redirects to HTTPS when SECURE_IN_ADMINHTML is on and secure URL is HTTPS', function () {
        // Canonical "admin over HTTPS" config. Without this branch, operators
        // enabling HTTPS only for admin would silently still serve admin over
        // HTTP.
        httpsConfig('http://localhost/', 'https://localhost/', '1', '0');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $response = dispatchBefore('/admin/dashboard');

        expect($response->isRedirect())->toBeTrue();
        expect(locationHeader($response))->toStartWith('https://');
    });

    it('does not redirect when SECURE_IN_ADMINHTML is off even if secure URL is HTTPS', function () {
        // Regression guard for the `&&` precedence in shouldBeSecure():
        // the secure base URL being HTTPS alone must not be enough when the
        // operator has explicitly disabled HTTPS-in-admin.
        httpsConfig('http://localhost/', 'https://localhost/', '0', '0');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $response = dispatchBefore('/admin/dashboard');

        expect($response->isRedirect())->toBeFalse();
    });
});

describe('Front observer HTTPS enforcement — frontend branch', function () {
    afterEach(fn() => httpsResetConfig());

    it('does not redirect when SECURE_IN_FRONTEND is off', function () {
        httpsConfig('http://localhost/', 'https://localhost/', '0', '0');
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

        $response = dispatchBefore('/customer/account/login');

        expect($response->isRedirect())->toBeFalse();
    });

    it('redirects when unsecure base URL is already HTTPS (scheme consistency)', function () {
        // Mirror of the admin belt-and-braces case, but driven through
        // shouldUrlBeSecure()'s frontend short-circuit on unsecure_base_url.
        httpsConfig('https://localhost/', 'https://localhost/', '0', '1');
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

        $response = dispatchBefore('/customer/account/login');

        expect($response->isRedirect())->toBeTrue();
        expect(locationHeader($response))->toStartWith('https://');
    });
});
