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
 * The Front loop then re-enters and dispatches the no-route controller.
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
});
