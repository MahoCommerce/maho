<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ComposerPlugin\AttributeCompiler;

uses(Tests\MahoBackendTestCase::class);

require_once __DIR__ . '/_fixtures/ControllerOverrideFixtures.php';

/**
 * Implicit, inheritance-based controller overrides compiled at `composer dump-autoload`.
 *
 * A controller subclass of a route-owning controller that declares no route of its own
 * supersedes the base in `controllerLookup` — replacing the legacy `<routers><args><modules>`
 * XML chain for the attribute-routed case. These tests drive the compiler's detection
 * (collectControllerOverrides) and precedence (resolveMostDerived) directly via reflection,
 * since the compiler has no standalone test harness.
 */

/** Seed AttributeCompiler::$data['routes'] so the given classes are route-owners. */
function seedRouteOwners(array $classes): void
{
    $routes = [];
    foreach ($classes as $i => $class) {
        $routes['route_' . $i] = [
            'path' => '/testfront/index',
            'class' => $class,
            'action' => 'indexAction',
            'methods' => [],
            'defaults' => [],
            'requirements' => [],
            'area' => 'frontend',
            'module' => 'Test_Override',
            'controllerName' => 'index',
            'pathVariables' => [],
        ];
    }
    $prop = new ReflectionProperty(AttributeCompiler::class, 'data');
    $prop->setValue(null, [
        'observers' => [],
        'crontab' => [],
        'routes' => $routes,
        'reverseLookup' => [],
        'controllerLookup' => [],
    ]);

    $overrides = new ReflectionProperty(AttributeCompiler::class, 'controllerOverrides');
    $overrides->setValue(null, []);
}

/** @return array<class-string, class-string> base → override winner */
function runCollectOverrides(array $scannedClassesInOrder, ?Closure $log = null): array
{
    // collectControllerOverrides takes a class => path map; only the keys (in order) matter.
    $scanned = [];
    foreach ($scannedClassesInOrder as $class) {
        $scanned[$class] = 'fixture://' . $class;
    }
    $method = new ReflectionMethod(AttributeCompiler::class, 'collectControllerOverrides');
    $method->invoke(null, $scanned, $log);

    return (new ReflectionProperty(AttributeCompiler::class, 'controllerOverrides'))->getValue();
}

describe('AttributeCompiler::collectControllerOverrides()', function () {
    it('records a subclass that declares no route as an override of its base', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_ChildController::class,
        ]);

        expect($overrides)->toBe([
            Test_Override_BaseController::class => Test_Override_ChildController::class,
        ]);
    });

    it('picks the most-derived class along a single inheritance chain', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_ChildController::class,
            Test_Override_GrandchildController::class,
        ]);

        expect($overrides[Test_Override_BaseController::class])
            ->toBe(Test_Override_GrandchildController::class);
    });

    it('resolves the chain structurally, independent of scan order', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        // Grandchild scanned before its parent — the winner is still the deepest class.
        $overrides = runCollectOverrides([
            Test_Override_GrandchildController::class,
            Test_Override_ChildController::class,
            Test_Override_BaseController::class,
        ]);

        expect($overrides[Test_Override_BaseController::class])
            ->toBe(Test_Override_GrandchildController::class);
    });

    it('skips abstract links but still attributes a concrete descendant to the base', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_AbstractController::class,
            Test_Override_ConcreteController::class,
        ]);

        expect($overrides[Test_Override_BaseController::class])
            ->toBe(Test_Override_ConcreteController::class);
    });

    it('records no override when the base has no route-less subclass', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_UnrelatedController::class,
        ]);

        expect($overrides)->toBe([]);
    });

    it('does not treat a subclass that owns its own routes as an override', function () {
        // Both base and child declare routes → child is its own controller, not an override.
        seedRouteOwners([
            Test_Override_BaseController::class,
            Test_Override_ChildController::class,
        ]);

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_ChildController::class,
        ]);

        expect($overrides)->toBe([]);
    });
});

describe('AttributeCompiler::collectControllerOverrides() — sibling conflicts', function () {
    it('reports a conflict and falls back to the last sibling in scan order', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        $logged = [];
        $log = function (string $level, string $message) use (&$logged): void {
            $logged[] = [$level, $message];
        };

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_SiblingAController::class,
            Test_Override_SiblingBController::class,
        ], $log);

        // Deterministic fallback: later in scan order (module load order) wins.
        expect($overrides[Test_Override_BaseController::class])
            ->toBe(Test_Override_SiblingBController::class);

        expect($logged)->toHaveCount(1);
        expect($logged[0][0])->toBe('error');
        expect($logged[0][1])->toContain('Controller override conflict');
        expect($logged[0][1])->toContain(Test_Override_SiblingAController::class);
        expect($logged[0][1])->toContain(Test_Override_SiblingBController::class);
    });

    it('reverses the fallback winner when scan order is reversed', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        $overrides = runCollectOverrides([
            Test_Override_BaseController::class,
            Test_Override_SiblingBController::class,
            Test_Override_SiblingAController::class,
        ]);

        expect($overrides[Test_Override_BaseController::class])
            ->toBe(Test_Override_SiblingAController::class);
    });
});

describe('AttributeCompiler::buildReverseLookup() — override application', function () {
    it('points controllerLookup at the override winner, not the base', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        // Record an override, then build the lookup.
        (new ReflectionProperty(AttributeCompiler::class, 'controllerOverrides'))
            ->setValue(null, [Test_Override_BaseController::class => Test_Override_ChildController::class]);

        (new ReflectionMethod(AttributeCompiler::class, 'buildReverseLookup'))->invoke(null, null);

        $data = (new ReflectionProperty(AttributeCompiler::class, 'data'))->getValue();

        expect($data['controllerLookup']['testfront/index'])
            ->toBe(Test_Override_ChildController::class);
    });

    it('keeps controllerLookup on the base when no override is recorded', function () {
        seedRouteOwners([Test_Override_BaseController::class]);

        (new ReflectionMethod(AttributeCompiler::class, 'buildReverseLookup'))->invoke(null, null);

        $data = (new ReflectionProperty(AttributeCompiler::class, 'data'))->getValue();

        expect($data['controllerLookup']['testfront/index'])
            ->toBe(Test_Override_BaseController::class);
    });
});
