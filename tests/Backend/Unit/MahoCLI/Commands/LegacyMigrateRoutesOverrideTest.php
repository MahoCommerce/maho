<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\LegacyMigrateRoutes;

uses(Tests\MahoBackendTestCase::class);

require_once __DIR__ . '/../../Maho/Routing/_fixtures/ControllerOverrideFixtures.php';

/**
 * Classification logic behind `legacy:migrate-routes`' controller-override migration.
 *
 * The command drops a `<routers><X><args><modules>` chain only when every declared override
 * is a clean subclass of a routed controller that re-implements inherited actions. These tests
 * drive the private classifiers (analyzeOverrideController / controllerOwnsRoutes /
 * hasSiblingConflict) directly, matching how the repo's other CLI command tests are written.
 */
function overrideCommand(): LegacyMigrateRoutes
{
    return new LegacyMigrateRoutes('legacy:migrate-routes');
}

function invokeMigratePrivate(string $method, array $args): mixed
{
    $ref = new ReflectionMethod(LegacyMigrateRoutes::class, $method);
    return $ref->invokeArgs(overrideCommand(), $args);
}

describe('LegacyMigrateRoutes::controllerOwnsRoutes()', function () {
    it('is true for a controller that declares #[Route] attributes', function () {
        expect(invokeMigratePrivate('controllerOwnsRoutes', [new ReflectionClass(Mage_Checkout_CartController::class)]))
            ->toBeTrue();
    });

    it('is false for a plain controller with no route attributes', function () {
        expect(invokeMigratePrivate('controllerOwnsRoutes', [new ReflectionClass(Test_Override_BaseController::class)]))
            ->toBeFalse();
    });
});

describe('LegacyMigrateRoutes::analyzeOverrideController()', function () {
    it('classifies a clean override of a routed controller as pure', function () {
        $result = invokeMigratePrivate('analyzeOverrideController', [Fixture_Xml_CartController::class]);

        expect($result['pure'])->toBeTrue();
        expect($result['base'])->toBe(Mage_Checkout_CartController::class);
        expect($result['newActions'])->toBe([]);
    });

    it('flags un-routed actions the subclass introduces', function () {
        $result = invokeMigratePrivate('analyzeOverrideController', [Fixture_NewAction_CartController::class]);

        expect($result['pure'])->toBeTrue();
        expect($result['base'])->toBe(Mage_Checkout_CartController::class);
        expect($result['newActions'])->toBe(['brandNew']);
    });

    it('is not pure when the class extends nothing', function () {
        $result = invokeMigratePrivate('analyzeOverrideController', [Test_Override_UnrelatedController::class]);

        expect($result['pure'])->toBeFalse();
        expect($result['base'])->toBeNull();
        expect($result['reason'])->toContain('extends no controller');
    });

    it('is not pure when the parent chain owns no routes', function () {
        $result = invokeMigratePrivate('analyzeOverrideController', [Test_Override_ChildController::class]);

        expect($result['pure'])->toBeFalse();
        expect($result['base'])->toBeNull();
        expect($result['reason'])->toContain('no routed controller');
    });
});

describe('LegacyMigrateRoutes::hasSiblingConflict()', function () {
    it('is false for a single inheritance chain', function () {
        expect(invokeMigratePrivate('hasSiblingConflict', [[
            Test_Override_ChildController::class,
            Test_Override_GrandchildController::class,
        ]]))->toBeFalse();
    });

    it('is true for two incomparable siblings', function () {
        expect(invokeMigratePrivate('hasSiblingConflict', [[
            Test_Override_SiblingAController::class,
            Test_Override_SiblingBController::class,
        ]]))->toBeTrue();
    });

    it('is false for a single class', function () {
        expect(invokeMigratePrivate('hasSiblingConflict', [[Test_Override_ChildController::class]]))
            ->toBeFalse();
    });
});
