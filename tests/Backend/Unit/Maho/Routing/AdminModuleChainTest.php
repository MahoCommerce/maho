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
});
