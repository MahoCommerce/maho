<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Routing\ControllerDispatcher;

uses(Tests\MahoBackendTestCase::class);

require_once __DIR__ . '/_fixtures/ControllerOverrideFixtures.php';

/**
 * Runtime side of inheritance-based controller overrides: ControllerDispatcher's
 * Symfony-matched dispatch path (resolveAttributeControllerClass) must consult the
 * compiled controllerLookup so an attribute/inheritance override supersedes the route's
 * `_maho_controller` default — while a legacy XML `<args><modules>` override still wins.
 */
function callResolveAttributeControllerClass(
    ControllerDispatcher $dispatcher,
    string $controllerName,
    string $area,
    string $frontName,
): ?string {
    $ref = new ReflectionMethod(ControllerDispatcher::class, 'resolveAttributeControllerClass');
    return $ref->invoke($dispatcher, $controllerName, $area, $frontName);
}

describe('ControllerDispatcher::resolveAttributeControllerClass()', function () {
    it('resolves a frontend route to its compiled controller when no XML override exists', function () {
        // Previously returned null (no XML chain) and the caller fell back to the route
        // default; now it consults the compiled lookup directly.
        expect(callResolveAttributeControllerClass(new ControllerDispatcher(), 'cart', 'frontend', 'checkout'))
            ->toBe(Mage_Checkout_CartController::class);
    });

    it('resolves an admin route to its compiled controller via the admin sentinel frontName', function () {
        expect(callResolveAttributeControllerClass(
            new ControllerDispatcher(),
            'dashboard',
            'adminhtml',
            \Maho\Routing\RouteCollectionBuilder::ADMIN_SENTINEL,
        ))->toBe(Mage_Adminhtml_DashboardController::class);
    });

    it('returns null for an unknown frontend controller', function () {
        expect(callResolveAttributeControllerClass(new ControllerDispatcher(), 'nosuchcontroller', 'frontend', 'checkout'))
            ->toBeNull();
    });

    it('lets a legacy XML <args><modules> override win over the compiled lookup', function () {
        // Register Fixture_Xml as a checkout override the M1 way.
        Mage::getConfig()->setNode('frontend/routers/checkout/args/modules', '', false);
        $modulesNode = Mage::getConfig()->getNode('frontend/routers/checkout/args/modules');
        $modulesNode->addChild('fixture_xml', 'Fixture_Xml');

        expect(callResolveAttributeControllerClass(new ControllerDispatcher(), 'cart', 'frontend', 'checkout'))
            ->toBe(Fixture_Xml_CartController::class);
    });
});
