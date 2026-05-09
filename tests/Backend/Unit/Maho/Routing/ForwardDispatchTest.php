<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Routing\ControllerDispatcher;

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for `_forward()` inside controllers whose class doesn't follow
 * the `Mage_Adminhtml`-flat layout — specifically, third-party Maho admin modules with
 * controllers under `controllers/Adminhtml/<Group>/`.
 *
 * Bug: `controllerLookup` previously stored the module name (`Maho_FeedManager`) and the
 * dispatcher reconstructed the class as `{module}_{ucwords(controller)}Controller`. That
 * gave `Maho_FeedManager_Feedmanager_FeedController`, which doesn't exist — the real class
 * has an `_Adminhtml_` infix. Initial dispatch worked because it pulled the class from the
 * matched route's `_maho_controller`, but `_forward()` re-entered via `dispatchForward()`,
 * which had to rebuild the class from `(frontName, controller)` and got it wrong → 404.
 *
 * Fix: `controllerLookup` now stores the FQCN directly. No reconstruction, no convention.
 */

function callResolveControllerClass(ControllerDispatcher $dispatcher, string $frontName, string $controllerName): ?string
{
    $ref = new ReflectionMethod(ControllerDispatcher::class, 'resolveControllerClass');
    return $ref->invoke($dispatcher, $frontName, $controllerName);
}

describe('ControllerDispatcher::resolveControllerClass()', function () {
    it('resolves Mage_Adminhtml controllers (flat layout, no Adminhtml infix)', function () {
        // app/code/core/Mage/Adminhtml/controllers/DashboardController.php
        expect(callResolveControllerClass(new ControllerDispatcher(), 'admin', 'dashboard'))
            ->toBe('Mage_Adminhtml_DashboardController');
    });

    it('resolves Mage_Adminhtml grouped controllers (e.g. Catalog/ProductController)', function () {
        // app/code/core/Mage/Adminhtml/controllers/Catalog/ProductController.php
        expect(callResolveControllerClass(new ControllerDispatcher(), 'admin', 'catalog_product'))
            ->toBe('Mage_Adminhtml_Catalog_ProductController');
    });

    it('resolves Maho-style admin modules with the _Adminhtml_ infix (regression)', function () {
        // app/code/core/Maho/FeedManager/controllers/Adminhtml/Feedmanager/FeedController.php
        // The module is `Maho_FeedManager` but the class has an `_Adminhtml_` segment
        // because the controllers live under controllers/Adminhtml/. Pre-fix, the
        // forward-dispatch path missed this and dropped to noroute.
        expect(callResolveControllerClass(new ControllerDispatcher(), 'admin', 'feedmanager_feed'))
            ->toBe('Maho_FeedManager_Adminhtml_Feedmanager_FeedController');
    });

    it('resolves frontend controllers from the compiled lookup', function () {
        // app/code/core/Mage/Catalog/controllers/ProductController.php
        expect(callResolveControllerClass(new ControllerDispatcher(), 'catalog', 'product'))
            ->toBe('Mage_Catalog_ProductController');
    });

    it('returns null when neither legacy XML nor the compiled lookup knows the pair', function () {
        expect(callResolveControllerClass(new ControllerDispatcher(), 'no-such-frontname', 'index'))
            ->toBeNull();
    });
});

describe('ControllerDispatcher resolution for the forward-dispatch target', function () {
    /**
     * Exercises the inputs `dispatchForward()` feeds into resolveControllerClass — frontName
     * `admin`, controllerName `feedmanager_feed` — to prove the resolver picks the right class
     * and that the target action exists. We stop short of invoking `dispatchForward()` itself
     * because that requires a full admin session/ACL setup; resolution + method existence is
     * the slice that broke before this PR (`dispatchForward()` saw null → false → 404).
     *
     * `editAction` is the real-world target of `newAction`'s `_forward('edit')` in FeedManager.
     */
    it('resolves admin/feedmanager_feed to the FQCN with editAction defined', function () {
        $dispatcher = new ControllerDispatcher();
        $resolved = callResolveControllerClass($dispatcher, 'admin', 'feedmanager_feed');

        // Pre-fix this returned null and dispatchForward returned false → noroute → 404.
        expect($resolved)->toBe('Maho_FeedManager_Adminhtml_Feedmanager_FeedController');
        expect(class_exists($resolved))->toBeTrue();
        expect(method_exists($resolved, 'editAction'))->toBeTrue();
    });
});
