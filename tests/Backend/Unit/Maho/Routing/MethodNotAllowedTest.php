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
 * `Mage_Core_Controller_Varien_Router_Default::_matchSymfony` must translate
 * a Symfony `MethodNotAllowedException` into an HTTP 405 response with an
 * `Allow` header listing the accepted verbs — rather than silently falling
 * through to the legacy-path parser and ultimately a 404 no-route page.
 *
 * The route used here is `wishlist.index.add` at `/wishlist/index/add`, which
 * is restricted to POST via `#[Maho\Config\Route(..., methods: ['POST'])]` in
 * Mage_Wishlist_IndexController::addAction. Hitting it with GET is what a
 * crawler or a mis-configured client does; the server must answer 405 (not 404)
 * so the client can retry with the correct verb.
 */

function methodNotAllowedRequest(string $pathInfo, string $httpMethod): Mage_Core_Controller_Request_Http
{
    $symfonyRequest = SymfonyRequest::create($pathInfo, $httpMethod);
    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    $request->setPathInfo($pathInfo);
    $request->setDispatched(false);
    return $request;
}

function findAllowHeader(Mage_Core_Controller_Response_Http $response): ?string
{
    foreach ($response->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Allow') === 0) {
            return $header['value'];
        }
    }
    return null;
}

describe('Default router 405 handling for method-restricted routes', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        // Replace the shared response so header/code assertions aren't polluted
        // by bootstrap or prior-test state.
        Mage::app()->setResponse(new Mage_Core_Controller_Response_Http());
    });

    it('returns 405 (not 404) when a POST-only route is hit with GET', function () {
        $router  = new Mage_Core_Controller_Varien_Router_Default();
        $request = methodNotAllowedRequest('/wishlist/index/add', 'GET');

        $matched = $router->match($request);
        $response = Mage::app()->getResponse();

        expect($matched)->toBeTrue();
        expect($response->getHttpResponseCode())->toBe(405);
    });

    it('sets an Allow header containing the compiled route\'s permitted methods', function () {
        $router  = new Mage_Core_Controller_Varien_Router_Default();
        $request = methodNotAllowedRequest('/wishlist/index/add', 'GET');

        $router->match($request);
        $response = Mage::app()->getResponse();

        $allow = findAllowHeader($response);
        expect($allow)->not->toBeNull();
        // The route declares methods: ['POST']. Implode is ', ' in source;
        // assert containment rather than exact match in case additional verbs
        // are ever added to the route or the separator is normalised later.
        expect($allow)->toContain('POST');
    });

    it('marks the request dispatched so the front controller does not re-enter', function () {
        $router  = new Mage_Core_Controller_Varien_Router_Default();
        $request = methodNotAllowedRequest('/wishlist/index/add', 'GET');

        $router->match($request);

        // Without this, Mage_Core_Controller_Varien_Front::dispatch()'s while
        // loop would keep asking routers to match until MAX_LOOP, burning CPU
        // and eventually raising a "Front controller reached 100 router match
        // iterations" exception.
        expect($request->isDispatched())->toBeTrue();
    });

    it('does not raise MethodNotAllowedException when the HTTP method is allowed', function () {
        // Sanity/control: the 405 branch must only fire on a method mismatch.
        // Asserted at the matcher level (not through full dispatch) because
        // dispatching a real controller would start a session here and conflict
        // with the bootstrap session state. If the compiled matcher happily
        // resolves /wishlist/index/add under POST (its declared method), the
        // 405 branch is never reached — which is the invariant we care about.
        $context = new \Symfony\Component\Routing\RequestContext();
        $context->setMethod('POST');
        $matcher = \Maho\Routing\RouteCollectionBuilder::createMatcher($context);

        $params = $matcher->match('/wishlist/index/add');

        expect($params)->toBeArray();
        expect($params['_route'] ?? null)->toBe('wishlist.index.add');
    });
});
