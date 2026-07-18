<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\LegacyMigrateRoutes;
use Symfony\Component\Console\Output\BufferedOutput;

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

describe('LegacyMigrateRoutes::migrateOverrideModulesNode() — empty <modules>', function () {
    function callMigrateOverrideModulesNode(
        LegacyMigrateRoutes $cmd,
        DOMElement $modulesNode,
        bool $dryRun,
        array $conflictedBases,
        int &$removed,
        int &$skipped,
        bool &$fileChanged,
        BufferedOutput $output,
    ): void {
        $ref = new ReflectionMethod(LegacyMigrateRoutes::class, 'migrateOverrideModulesNode');
        $ref->invokeArgs($cmd, [
            $modulesNode,
            'frontend/checkout',
            $output,
            $dryRun,
            function (): void {},
            &$removed,
            &$skipped,
            &$fileChanged,
            $conflictedBases,
        ]);
    }

    function modulesNodeFromXml(string $xml): DOMElement
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        /** @var DOMElement $node */
        $node = $dom->getElementsByTagName('modules')->item(0);
        return $node;
    }

    it('counts an empty <modules> wrapper as removed (not skipped) in dry-run', function () {
        $node = modulesNodeFromXml('<config><frontend><routers><checkout><args><modules></modules></args></checkout></routers></frontend></config>');
        $removed = 0;
        $skipped = 0;
        $fileChanged = false;
        callMigrateOverrideModulesNode(overrideCommand(), $node, true, [], $removed, $skipped, $fileChanged, new BufferedOutput());

        expect($removed)->toBe(1);
        expect($skipped)->toBe(0);
        expect($fileChanged)->toBeFalse();
        // Dry-run leaves the XML in place.
        expect($node->parentNode)->not->toBeNull();
    });

    it('removes an empty <modules> wrapper and counts it as removed in apply mode', function () {
        $node = modulesNodeFromXml('<config><frontend><routers><checkout><args><modules></modules></args></checkout></routers></frontend></config>');
        $removed = 0;
        $skipped = 0;
        $fileChanged = false;
        callMigrateOverrideModulesNode(overrideCommand(), $node, false, [], $removed, $skipped, $fileChanged, new BufferedOutput());

        expect($removed)->toBe(1);
        expect($skipped)->toBe(0);
        expect($fileChanged)->toBeTrue();
        // Apply mode detaches the dead node.
        expect($node->parentNode)->toBeNull();
    });
});

describe('LegacyMigrateRoutes — cross-file sibling conflict pre-pass', function () {
    it('blocks removal when two modules independently override the same base across files', function () {
        // Stand up two throwaway modules on disk so findControllers() discovers the sibling
        // override classes (already loaded as fixtures). Point userCodeDirs at the temp pool.
        // findControllers() builds paths from MAHO_ROOT_DIR, which only the CLI/web entry points
        // define — the test process doesn't, so seed it with the repo root.
        if (!defined('MAHO_ROOT_DIR')) {
            define('MAHO_ROOT_DIR', dirname(__DIR__, 5));
        }
        $tmpRel = 'var/legacy-override-test-' . uniqid();
        $tmpAbs = MAHO_ROOT_DIR . '/' . $tmpRel;
        foreach (['SiblingX', 'SiblingY'] as $mod) {
            $dir = "$tmpAbs/Fixture/$mod/controllers";
            mkdir($dir, 0o777, true);
            file_put_contents("$dir/CartController.php", "<?php\n");
        }

        try {
            $cmd = overrideCommand();
            (new ReflectionProperty(LegacyMigrateRoutes::class, 'userCodeDirs'))->setValue($cmd, [$tmpRel]);

            $configXml = fn(string $prefix): string => sprintf(
                '<config><frontend><routers><checkout><args><modules><m>%s</m></modules></args></checkout></routers></frontend></config>',
                $prefix,
            );
            $domX = new DOMDocument();
            $domX->loadXML($configXml('Fixture_SiblingX'));
            $domY = new DOMDocument();
            $domY->loadXML($configXml('Fixture_SiblingY'));

            // Reproduce execute()'s pre-pass: accumulate clean overrides across both files.
            $collect = new ReflectionMethod(LegacyMigrateRoutes::class, 'collectCleanOverridesByBase');
            $hasConflict = new ReflectionMethod(LegacyMigrateRoutes::class, 'hasSiblingConflict');
            $global = [];
            foreach ([$domX, $domY] as $dom) {
                foreach ($collect->invoke($cmd, $dom) as $base => $classes) {
                    foreach ($classes as $class) {
                        $global[$base][] = $class;
                    }
                }
            }
            $conflictedBases = [];
            foreach ($global as $base => $classes) {
                if (count($classes) > 1 && $hasConflict->invoke($cmd, $classes)) {
                    $conflictedBases[] = $base;
                }
            }

            expect($conflictedBases)->toContain(Mage_Checkout_CartController::class);

            // Feed one file's <modules> node into the per-node migrator with the cross-file
            // conflict known: it must be left in place (skipped), not silently removed.
            /** @var DOMElement $node */
            $node = $domX->getElementsByTagName('modules')->item(0);
            $removed = 0;
            $skipped = 0;
            $fileChanged = false;
            $output = new BufferedOutput();
            callMigrateOverrideModulesNode($cmd, $node, false, $conflictedBases, $removed, $skipped, $fileChanged, $output);

            expect($skipped)->toBe(1);
            expect($removed)->toBe(0);
            expect($fileChanged)->toBeFalse();
            expect($node->parentNode)->not->toBeNull();
            expect($output->fetch())->toContain('across modules');
        } finally {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpAbs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($it as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($tmpAbs);
        }
    });
});
