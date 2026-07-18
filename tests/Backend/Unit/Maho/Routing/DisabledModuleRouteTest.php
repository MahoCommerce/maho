<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Routing\ControllerDispatcher;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * A route can linger in the compiled matcher after its module is disabled in
 * app/etc/modules/*.xml (the compiled table only refreshes on `composer
 * dump-autoload`). The dispatcher must treat such routes as a miss and let the
 * router fall through to the no-route handler, rather than instantiating a
 * controller whose module config never loaded. See issue #1053.
 */

describe('Disabled module routes do not dispatch', function () {
    it('returns false for a route whose module is not enabled', function () {
        $dispatcher = new ControllerDispatcher();
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
        $response = new Mage_Core_Controller_Response_Http();

        // Valid, dispatchable controller + action so the only reason dispatch can
        // fail is the module-enabled guard, not class/action resolution.
        $params = [
            '_maho_controller' => Mage_Cms_IndexController::class,
            '_maho_action' => 'indexAction',
            '_maho_module' => 'Maho_NotInstalledModule',
            '_maho_controller_name' => 'index',
            '_maho_front_name' => 'cms',
            '_maho_area' => 'frontend',
        ];

        expect($dispatcher->dispatch($params, $request, $response))->toBeFalse();
        expect($request->isDispatched())->toBeFalse();
    });
});
