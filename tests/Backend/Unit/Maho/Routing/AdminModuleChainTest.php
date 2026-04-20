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
 * Register an extension module in the admin override chain, as a third-party
 * module would via config.xml's <admin><routers><adminhtml><args><modules>.
 */
function registerAdminModule(string $nodeName, string $moduleName, ?string $before = null, ?string $after = null): void
{
    // Ensure the intermediate path exists without clobbering existing children.
    Mage::getConfig()->setNode('admin/routers/adminhtml/args/modules', '', false);
    $modulesNode = Mage::getConfig()->getNode('admin/routers/adminhtml/args/modules');
    $child = $modulesNode->addChild($nodeName, $moduleName);
    if ($before !== null) {
        $child->addAttribute('before', $before);
    }
    if ($after !== null) {
        $child->addAttribute('after', $after);
    }
}

function callBuildAdminModuleChain(ControllerDispatcher $dispatcher): array
{
    $ref = new ReflectionMethod(ControllerDispatcher::class, 'buildAdminModuleChain');
    return $ref->invoke($dispatcher);
}

describe('ControllerDispatcher::buildAdminModuleChain()', function () {
    it('returns only Mage_Adminhtml when no custom modules are registered', function () {
        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Mage_Adminhtml']);
    });

    it('appends a module with no before/after to the end of the chain', function () {
        registerAdminModule('company_extension', 'Company_Extension');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Mage_Adminhtml', 'Company_Extension']);
    });

    it('places a module with before="Mage_Adminhtml" ahead of core', function () {
        registerAdminModule('company_extension', 'Company_Extension', before: 'Mage_Adminhtml');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Company_Extension', 'Mage_Adminhtml']);
    });

    it('places a module with after="Mage_Adminhtml" directly after core', function () {
        registerAdminModule('company_extension', 'Company_Extension', after: 'Mage_Adminhtml');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Mage_Adminhtml', 'Company_Extension']);
    });

    it('respects relative ordering when multiple modules use before/after', function () {
        registerAdminModule('first', 'A_First', before: 'Mage_Adminhtml');
        registerAdminModule('last', 'Z_Last', after: 'Mage_Adminhtml');
        registerAdminModule('middle', 'M_Middle', before: 'Z_Last');

        $chain = callBuildAdminModuleChain(new ControllerDispatcher());

        expect($chain)->toBe(['A_First', 'Mage_Adminhtml', 'M_Middle', 'Z_Last']);
    });

    it('falls back to prepending when the before target is unknown', function () {
        registerAdminModule('orphan', 'Orphan_Module', before: 'Nonexistent_Module');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Orphan_Module', 'Mage_Adminhtml']);
    });

    it('falls back to appending when the after target is unknown', function () {
        registerAdminModule('orphan', 'Orphan_Module', after: 'Nonexistent_Module');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Mage_Adminhtml', 'Orphan_Module']);
    });

    it('skips nodes with empty values', function () {
        Mage::getConfig()->setNode('admin/routers/adminhtml/args/modules', '', false);
        $modulesNode = Mage::getConfig()->getNode('admin/routers/adminhtml/args/modules');
        $modulesNode->addChild('blank', '');
        $modulesNode->addChild('valid', 'Valid_Module');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['Mage_Adminhtml', 'Valid_Module']);
    });

    it('inserts later before-siblings between earlier ones and the target', function () {
        // Both want to go before Mage_Adminhtml. The one registered first lands
        // at position 0; the second lands at Mage_Adminhtml's new position,
        // which pushes it between the first module and Mage_Adminhtml.
        registerAdminModule('first', 'A_First', before: 'Mage_Adminhtml');
        registerAdminModule('second', 'B_Second', before: 'Mage_Adminhtml');

        expect(callBuildAdminModuleChain(new ControllerDispatcher()))
            ->toBe(['A_First', 'B_Second', 'Mage_Adminhtml']);
    });

    it('prefers "before" when a single node has both before and after attributes', function () {
        // Defensive: config XML should not combine both, but if it does the
        // implementation must pick one deterministically instead of doubling up.
        registerAdminModule('both', 'Both_Module', before: 'Mage_Adminhtml', after: 'Mage_Adminhtml');

        $chain = callBuildAdminModuleChain(new ControllerDispatcher());

        expect($chain)->toBe(['Both_Module', 'Mage_Adminhtml']);
        expect(array_count_values($chain)['Both_Module'])->toBe(1);
    });

    it('does not loop infinitely on circular before references', function () {
        // A says before=B, B says before=A. Neither target is in the list yet
        // when processed, so each falls back to prepend. The point of the test
        // is to prove the builder terminates with a deterministic output.
        registerAdminModule('circ_a', 'Circ_A', before: 'Circ_B');
        registerAdminModule('circ_b', 'Circ_B', before: 'Circ_A');

        $chain = callBuildAdminModuleChain(new ControllerDispatcher());

        expect($chain)->toContain('Mage_Adminhtml', 'Circ_A', 'Circ_B');
        expect(count($chain))->toBe(3);
    });
});
