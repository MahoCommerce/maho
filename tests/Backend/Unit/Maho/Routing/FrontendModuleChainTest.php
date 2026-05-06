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
 * Register a third-party frontend module override under
 * <frontend><routers><{routerCode}><args><modules><{nodeName}/>, the M1 pattern
 * for piggybacking on a core route to override its controllers.
 */
function registerFrontendOverride(
    string $routerCode,
    string $nodeName,
    string $moduleName,
    ?string $before = null,
    ?string $after = null,
    ?string $declaredFrontName = null,
): void {
    Mage::getConfig()->setNode("frontend/routers/{$routerCode}/args/modules", '', false);
    if ($declaredFrontName !== null) {
        Mage::getConfig()->setNode("frontend/routers/{$routerCode}/args/frontName", $declaredFrontName, false);
    }
    $modulesNode = Mage::getConfig()->getNode("frontend/routers/{$routerCode}/args/modules");
    $child = $modulesNode->addChild($nodeName, $moduleName);
    if ($before !== null) {
        $child->addAttribute('before', $before);
    }
    if ($after !== null) {
        $child->addAttribute('after', $after);
    }
}

function callBuildFrontendModuleChain(ControllerDispatcher $dispatcher, string $frontName): array
{
    $ref = new ReflectionMethod(ControllerDispatcher::class, 'buildFrontendModuleChain');
    return $ref->invoke($dispatcher, $frontName);
}

describe('ControllerDispatcher::buildFrontendModuleChain()', function () {
    it('returns an empty array when no override is registered for the frontName', function () {
        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe([]);
    });

    it('returns an empty array for an empty frontName', function () {
        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), ''))
            ->toBe([]);
    });

    it('matches by router-code element name when <args><frontName> is absent', function () {
        registerFrontendOverride('customer', 'vendor_x', 'Vendor_X');

        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe(['Vendor_X']);
    });

    it('matches by <args><frontName> when the router-code differs', function () {
        registerFrontendOverride(
            routerCode: 'aliasrouter',
            nodeName: 'vendor_x',
            moduleName: 'Vendor_X',
            declaredFrontName: 'customer',
        );

        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe(['Vendor_X']);
    });

    it('respects before ordering relative to other override entries', function () {
        registerFrontendOverride('customer', 'first', 'A_First');
        registerFrontendOverride('customer', 'second', 'B_Second', before: 'A_First');

        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe(['B_Second', 'A_First']);
    });

    it('respects after ordering relative to other override entries', function () {
        registerFrontendOverride('customer', 'first', 'A_First');
        registerFrontendOverride('customer', 'second', 'B_Second', after: 'A_First');

        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe(['A_First', 'B_Second']);
    });

    it('treats the lookup as case-insensitive on the frontName', function () {
        registerFrontendOverride('customer', 'vendor_x', 'Vendor_X');

        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'CUSTOMER'))
            ->toBe(['Vendor_X']);
    });

    it('skips routers whose frontName does not match the request', function () {
        registerFrontendOverride('customer', 'vendor_c', 'Vendor_C');
        registerFrontendOverride('catalog', 'vendor_cat', 'Vendor_Cat');

        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe(['Vendor_C']);
    });

    it('caches the chain per dispatcher instance', function () {
        registerFrontendOverride('customer', 'vendor_x', 'Vendor_X');

        $dispatcher = new ControllerDispatcher();
        $first = callBuildFrontendModuleChain($dispatcher, 'customer');

        // Mutate config: a fresh dispatcher would now see two entries, but the
        // cached one keeps the original list.
        registerFrontendOverride('customer', 'vendor_y', 'Vendor_Y');

        $second = callBuildFrontendModuleChain($dispatcher, 'customer');
        expect($second)->toBe($first);

        // Fresh dispatcher sees the updated config.
        expect(callBuildFrontendModuleChain(new ControllerDispatcher(), 'customer'))
            ->toBe(['Vendor_X', 'Vendor_Y']);
    });
});
