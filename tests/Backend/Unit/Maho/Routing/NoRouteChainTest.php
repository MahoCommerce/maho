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
 * When the Symfony matcher + legacy-path fallback both miss, the default router
 * sets the no-route target (cms/index/noRoute) on the request and returns true.
 * The Front loop re-enters, dispatches the no-route controller, and the CMS
 * 404 page renders. These tests cover the first half of that chain without
 * requiring a full rendering pipeline.
 */

function frontendRequest(string $pathInfo): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
    $request->setPathInfo($pathInfo);
    $request->setDispatched(false);
    return $request;
}

describe('Default router no-route fallback', function () {
    beforeEach(function () {
        // Ensure we use the configured frontend store (not admin).
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

    it('sets cms/index/noRoute on the request when no route matches', function () {
        $router = new Mage_Core_Controller_Varien_Router_Default();
        $request = frontendRequest('/totally-unknown-path');

        $matched = $router->match($request);

        expect($matched)->toBeTrue();
        expect($request->getModuleName())->toBe('cms');
        expect($request->getControllerName())->toBe('index');
        expect($request->getActionName())->toBe('noRoute');
        expect($request->isDispatched())->toBeFalse();
    });

    it('reads the no-route target from the web/default/no_route store config', function () {
        $noRoute = Mage::app()->getStore()->getConfig('web/default/no_route');
        expect($noRoute)->toBe('cms/index/noRoute');
    });
});

describe('No-route forward chain resolution', function () {
    it('resolves cms/index/noRoute to a real controller action', function () {
        // After the default router sets cms/index/noRoute, the next loop
        // iteration calls dispatchForward() which must find the class + method.
        // Verify both without invoking dispatch (which would render the 404 page).
        expect(class_exists(Mage_Cms_IndexController::class))->toBeTrue();

        $request = frontendRequest('/totally-unknown-path');
        $request->setModuleName('cms')->setControllerName('index')->setActionName('noRoute');
        $response = new Mage_Core_Controller_Response_Http();
        $controller = new Mage_Cms_IndexController($request, $response);

        expect($controller->hasAction('noRoute'))->toBeTrue();
    });

    it('cms/index has a defaultNoRoute fallback action for when the CMS 404 page is not configured', function () {
        // The CMS noroute action falls forward to defaultNoRoute if the store's
        // no-route CMS page is missing. Guarantees the chain always terminates.
        $request = frontendRequest('/');
        $response = new Mage_Core_Controller_Response_Http();
        $controller = new Mage_Cms_IndexController($request, $response);

        expect($controller->hasAction('defaultNoRoute'))->toBeTrue();
    });
});
