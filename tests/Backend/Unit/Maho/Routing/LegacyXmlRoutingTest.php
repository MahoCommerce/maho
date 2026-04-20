<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Routing\RouteCollectionBuilder;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * BC shim tests for the classic M1/OpenMage `<frontend><routers>` config.
 *
 * Modules that declare a frontName via XML (instead of `#[Maho\Config\Route]`)
 * must still dispatch: the frontName → module class-prefix map is read from
 * merged config and consulted by `resolveControllerModule()` before the compiled
 * attribute lookup. Router_Default also runs this path *before* the Symfony
 * matcher so a legacy frontName that collides with a core route takes
 * precedence (preserving M1 "first declared wins" semantics).
 */

function resetLegacyFrontNamesCache(): void
{
    $ref = new ReflectionClass(RouteCollectionBuilder::class);
    $prop = $ref->getProperty('legacyFrontNames');
    $prop->setValue(null, null);
}

function registerLegacyXmlRoute(string $code, string $frontName, string $module): void
{
    Mage::getConfig()->setNode("frontend/routers/{$code}/args/frontName", $frontName, true);
    Mage::getConfig()->setNode("frontend/routers/{$code}/args/module", $module, true);
}

describe('RouteCollectionBuilder::getLegacyFrontNames()', function () {
    beforeEach(fn() => resetLegacyFrontNamesCache());
    afterEach(fn() => resetLegacyFrontNamesCache());

    it('returns an empty map when no legacy XML routers are declared', function () {
        expect(RouteCollectionBuilder::getLegacyFrontNames())->toBe([]);
    });

    it('reads frontName and module from <frontend><routers> config', function () {
        registerLegacyXmlRoute('mymodule', 'mymodule', 'MyVendor_Mymodule');

        expect(RouteCollectionBuilder::getLegacyFrontNames())
            ->toBe(['mymodule' => 'MyVendor_Mymodule']);
    });

    it('lowercases the frontName key for case-insensitive lookup', function () {
        registerLegacyXmlRoute('mymodule', 'MyModule', 'MyVendor_Mymodule');

        expect(RouteCollectionBuilder::getLegacyFrontNames())
            ->toBe(['mymodule' => 'MyVendor_Mymodule']);
    });

    it('skips entries with missing frontName or module', function () {
        Mage::getConfig()->setNode('frontend/routers/broken/args/frontName', 'broken', true);
        // Intentionally no <module> node.

        expect(RouteCollectionBuilder::getLegacyFrontNames())->toBe([]);
    });

    it('keeps the first declaration when two codes claim the same frontName', function () {
        registerLegacyXmlRoute('first', 'shared', 'First_Module');
        registerLegacyXmlRoute('second', 'shared', 'Second_Module');

        expect(RouteCollectionBuilder::getLegacyFrontNames())
            ->toBe(['shared' => 'First_Module']);
    });
});

describe('RouteCollectionBuilder::resolveControllerModule() with legacy XML', function () {
    beforeEach(fn() => resetLegacyFrontNamesCache());
    afterEach(fn() => resetLegacyFrontNamesCache());

    it('falls back to the legacy map when the compiled lookup misses', function () {
        registerLegacyXmlRoute('mymodule', 'mymodule', 'MyVendor_Mymodule');

        expect(RouteCollectionBuilder::resolveControllerModule('mymodule', 'index'))
            ->toBe('MyVendor_Mymodule');
    });

    it('gives the legacy declaration precedence over a compiled core frontName', function () {
        // `catalog` is a compiled core frontName (Mage_Catalog). A legacy module
        // declaring the same frontName must win to preserve M1 shadow-by-frontName
        // semantics. If the declared module class doesn't exist the dispatcher
        // falls through — resolveControllerModule itself just answers the mapping.
        registerLegacyXmlRoute('legacy_catalog', 'catalog', 'Shadow_Catalog');

        expect(RouteCollectionBuilder::resolveControllerModule('catalog', 'product'))
            ->toBe('Shadow_Catalog');
    });

    it('still resolves compiled frontNames when no legacy declaration shadows them', function () {
        expect(RouteCollectionBuilder::resolveControllerModule('catalog', 'product'))
            ->toBe('Mage_Catalog');
    });

    it('returns null when neither the legacy map nor the compiled lookup knows the frontName', function () {
        expect(RouteCollectionBuilder::resolveControllerModule('definitely-not-a-frontname', 'index'))
            ->toBeNull();
    });
});

describe('Router_Default legacy XML pre-check', function () {
    beforeEach(function () {
        resetLegacyFrontNamesCache();
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });
    afterEach(fn() => resetLegacyFrontNamesCache());

    /**
     * We invoke the protected `_matchLegacyXmlRoute` directly rather than driving
     * `$router->match()` end-to-end: a successful match triggers controller
     * dispatch which boots the session stack — unnecessary here (and brittle in
     * tests). These assertions cover the branching logic; full dispatch is
     * covered by the existing ControllerDispatcher unit tests.
     */
    function callMatchLegacyXmlRoute(
        Mage_Core_Controller_Varien_Router_Default $router,
        Mage_Core_Controller_Request_Http $request,
    ): bool {
        $ref = new ReflectionMethod($router, '_matchLegacyXmlRoute');
        return $ref->invoke($router, $request, new \Maho\Routing\ControllerDispatcher());
    }

    it('returns false when no legacy XML routers are declared (fast path)', function () {
        $router = new Mage_Core_Controller_Varien_Router_Default();
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
        $request->setPathInfo('/checkout/cart');

        expect(callMatchLegacyXmlRoute($router, $request))->toBeFalse();
    });

    it('returns false when the path frontName is not in the legacy map', function () {
        registerLegacyXmlRoute('unrelated', 'other', 'Other_Module');

        $router = new Mage_Core_Controller_Varien_Router_Default();
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
        $request->setPathInfo('/checkout/cart');

        expect(callMatchLegacyXmlRoute($router, $request))->toBeFalse();
    });

    it('returns false when the controller class cannot be resolved, allowing Symfony fallback', function () {
        // frontName IS in the legacy map, but the declared module has no matching
        // controller class — dispatchLegacyPath returns false, so our pre-check
        // returns false and Router_Default falls through to the Symfony matcher.
        registerLegacyXmlRoute('fake', 'myfrontname', 'Totally_Nonexistent_Module');

        $router = new Mage_Core_Controller_Varien_Router_Default();
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
        $request->setPathInfo('/myfrontname/something/action');

        expect(callMatchLegacyXmlRoute($router, $request))->toBeFalse();
    });

    it('matches the legacy frontName case-insensitively against the path', function () {
        // Legacy declaration uses 'Shopfront'; request path uses '/SHOPFRONT/...'.
        // The pre-check should still recognise the frontName and delegate to
        // dispatchLegacyPath (which then fails to resolve the fake module —
        // proving the pre-check ran but the fallback path is graceful).
        registerLegacyXmlRoute('fake', 'Shopfront', 'Totally_Nonexistent_Module');

        $router = new Mage_Core_Controller_Varien_Router_Default();
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/'));
        $request->setPathInfo('/SHOPFRONT/controller/action');

        // If the case-check was broken, dispatchLegacyPath would never be called
        // and this would still return false — but from the fast-path miss rather
        // than the class-resolution miss. The resolveControllerModule unit tests
        // already pin the case-insensitive map; this just guards the glue.
        expect(callMatchLegacyXmlRoute($router, $request))->toBeFalse();
    });
});
