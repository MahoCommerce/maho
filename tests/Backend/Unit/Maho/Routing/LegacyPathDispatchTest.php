<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Routing\ControllerDispatcher;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * dispatchLegacyPath() is the fallback that catches URL rewrites whose targets
 * are stored in the legacy `module/controller/action/k/v` form — including all
 * DB-persisted catalog/CMS rewrites carried over from pre-Symfony versions.
 */

function legacyRequest(string $pathInfo): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
    $request->setPathInfo($pathInfo);
    return $request;
}

describe('ControllerDispatcher::dispatchLegacyPath()', function () {
    it('returns false on an empty path', function () {
        $dispatcher = new ControllerDispatcher();
        $result = $dispatcher->dispatchLegacyPath(legacyRequest('/'), new Mage_Core_Controller_Response_Http());

        expect($result)->toBeFalse();
    });

    it('returns false when the frontName does not resolve to a controller module', function () {
        $dispatcher = new ControllerDispatcher();
        $result = $dispatcher->dispatchLegacyPath(
            legacyRequest('/madeupfront/controller/action'),
            new Mage_Core_Controller_Response_Http(),
        );

        expect($result)->toBeFalse();
    });

    it('parses module/controller/action from the path and sets them on the request', function () {
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog/index/nonexistentAction');

        // Dispatch will bail at hasAction() since the action does not exist,
        // but parsing of the path into request state should have completed.
        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($request->getModuleName())->toBe('catalog');
        expect($request->getControllerName())->toBe('index');
        expect($request->getActionName())->toBe('nonexistentAction');
    });

    it('parses trailing key/value pairs into request params', function () {
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog/index/nonexistentAction/id/14/store/1');

        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($request->getParam('id'))->toBe('14');
        expect($request->getParam('store'))->toBe('1');
    });

    it('url-decodes key/value pairs', function () {
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog/index/nonexistentAction/slug/hello%20world');

        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($request->getParam('slug'))->toBe('hello world');
    });

    it('defaults controller to "index" and action to "index" when path segments are missing', function () {
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog');

        // Mage_Catalog_IndexController::indexAction() exists, so dispatch succeeds.
        // We only care that parsing defaults kick in — parse is observable pre-dispatch.
        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($request->getControllerName())->toBe('index');
        expect($request->getActionName())->toBe('index');
    });

    it('handles a trailing key without a value by storing an empty string', function () {
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog/index/nonexistentAction/id/14/orphan');

        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($request->getParam('id'))->toBe('14');
        expect($request->getParam('orphan'))->toBe('');
    });

    it('is case-insensitive on frontName and controllerName', function () {
        // resolveControllerClass() lowercases both lookup keys, so the legacy
        // path must continue to work even if an old rewrite used mixed case.
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/CATALOG/Index/nonexistentAction');

        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        // The request stores the segments as-written; the module resolves
        // because the lookup is case-insensitive.
        expect($request->getModuleName())->toBe('CATALOG');
        expect($request->getControllerName())->toBe('Index');
    });

    it('tolerates leading and trailing slashes', function () {
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog/index/nonexistentAction/');

        $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($request->getActionName())->toBe('nonexistentAction');
    });

    it('bails out when a path segment is empty (double slash)', function () {
        // '/catalog//nonexistentAction' explodes to ['catalog', '', 'nonexistentAction'].
        // The empty controllerName cannot resolve to a class, so the dispatcher
        // must return false rather than guessing or falling through to index.
        $dispatcher = new ControllerDispatcher();
        $request = legacyRequest('/catalog//nonexistentAction');

        $result = $dispatcher->dispatchLegacyPath($request, new Mage_Core_Controller_Response_Http());

        expect($result)->toBeFalse();
    });
});
