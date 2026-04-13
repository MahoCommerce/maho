<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

it('defines AREA_ADMIN as the primary constant', function () {
    expect(Mage_Core_Model_App_Area::AREA_ADMIN)->toBe('admin');
});

it('aliases AREA_ADMINHTML to AREA_ADMIN', function () {
    expect(Mage_Core_Model_App_Area::AREA_ADMINHTML)->toBe(Mage_Core_Model_App_Area::AREA_ADMIN);
    expect(Mage_Core_Model_App_Area::AREA_ADMINHTML)->toBe('admin');
});

it('loads admin area events and translate config from the admin section', function () {
    $config = Mage::getConfig();

    $layoutUpdates = $config->getNode('admin/layout/updates');
    expect($layoutUpdates)->not->toBeFalse('Layout updates should exist under admin/');

    $translate = $config->getNode('admin/translate/modules');
    expect($translate)->not->toBeFalse('Translate modules should exist under admin/');

    $adminhtmlNode = $config->getNode('adminhtml');
    $hasChildren = $adminhtmlNode !== false && $adminhtmlNode->hasChildren();
    expect($hasChildren)->toBeFalse('No <adminhtml> section should remain in merged config');
});

it('has no adminhtml area key in compiled attributes', function () {
    $compiled = Maho::getCompiledAttributes();

    expect($compiled['observers'])->not->toHaveKey('adminhtml');
    expect($compiled['observers'])->toHaveKey('admin');
    expect($compiled['observers']['admin'])->not->toBeEmpty();
});

it('resolves admin area observers from compiled attributes', function () {
    $compiled = Maho::getCompiledAttributes();
    $adminObservers = $compiled['observers']['admin'];

    // The admin auth observer should be registered under the admin area
    expect($adminObservers)->toHaveKey('controller_action_predispatch');

    $predispatchObservers = array_column($adminObservers['controller_action_predispatch'], 'name');
    expect($predispatchObservers)->toContain('auth');
});

it('merges third-party adminhtml config into admin via compat shim', function () {
    // Use a standalone Mage_Core_Model_Config so we don't pollute the global config
    $config = new Mage_Core_Model_Config();
    $config->loadString('<config><admin><routers/></admin></config>');

    // Simulate a third-party module with <adminhtml> section
    $thirdParty = new Mage_Core_Model_Config();
    $thirdParty->loadString('
        <config>
            <adminhtml>
                <events>
                    <test_compat_event>
                        <observers>
                            <test_observer>
                                <class>catalog/observer</class>
                                <method>testMethod</method>
                            </test_observer>
                        </observers>
                    </test_compat_event>
                </events>
            </adminhtml>
        </config>
    ');

    $config->extend($thirdParty, true);

    // Run the migration shim
    $ref = new ReflectionMethod($config, '_migrateAdminhtmlConfig');
    $ref->invoke($config);

    // The event should now be accessible under admin/events
    $eventConfig = $config->getNode('admin/events/test_compat_event/observers/test_observer');
    expect($eventConfig)->not->toBeFalse('Third-party adminhtml event should be merged into admin');
    expect((string) $eventConfig->class)->toBe('catalog/observer');

    // The adminhtml node should be cleaned up
    $adminhtmlNode = $config->getNode('adminhtml');
    $hasChildren = $adminhtmlNode !== false && $adminhtmlNode->hasChildren();
    expect($hasChildren)->toBeFalse('adminhtml node should be removed after migration');
});

it('shim preserves existing admin config when merging adminhtml', function () {
    $config = new Mage_Core_Model_Config();
    $config->loadString('
        <config>
            <admin>
                <routers>
                    <adminhtml>
                        <use>admin</use>
                    </adminhtml>
                </routers>
            </admin>
            <adminhtml>
                <translate>
                    <modules>
                        <ThirdParty><files><default>ThirdParty.csv</default></files></ThirdParty>
                    </modules>
                </translate>
            </adminhtml>
        </config>
    ');

    $ref = new ReflectionMethod($config, '_migrateAdminhtmlConfig');
    $ref->invoke($config);

    // Existing admin content should be preserved
    expect((string) $config->getNode('admin/routers/adminhtml/use'))->toBe('admin');

    // Merged content should be present
    $csvNode = $config->getNode('admin/translate/modules/ThirdParty/files/default');
    expect($csvNode)->not->toBeFalse();
    expect((string) $csvNode)->toBe('ThirdParty.csv');
});

it('shim is a no-op when no adminhtml section exists', function () {
    $config = new Mage_Core_Model_Config();
    $config->loadString('<config><admin><routers/></admin></config>');

    $ref = new ReflectionMethod($config, '_migrateAdminhtmlConfig');
    $ref->invoke($config);

    // Should not crash, admin section should be untouched
    expect($config->getNode('admin/routers'))->not->toBeFalse();
});

it('maps admin area to adminhtml design directory', function () {
    expect(Mage_Core_Model_Design_Package::areaToDesignDir('admin'))->toBe('adminhtml');
    expect(Mage_Core_Model_Design_Package::areaToDesignDir('frontend'))->toBe('frontend');

    $designPackage = Mage::getSingleton('core/design_package');
    $designPackage->setArea('admin');

    $baseDir = $designPackage->getBaseDir([
        '_area' => 'admin',
        '_package' => 'default',
        '_theme' => 'default',
        '_type' => 'template',
    ]);
    expect($baseDir)->toContain(DIRECTORY_SEPARATOR . 'adminhtml' . DIRECTORY_SEPARATOR);
    expect($baseDir)->not->toContain(DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'default');
});

it('resolves skin URLs with adminhtml directory for admin area', function () {
    $designPackage = Mage::getSingleton('core/design_package');
    $designPackage->setArea('admin');

    $skinDir = $designPackage->getSkinBaseDir([
        '_area' => 'admin',
        '_package' => 'default',
        '_theme' => 'default',
    ]);
    expect($skinDir)->toContain(DIRECTORY_SEPARATOR . 'adminhtml' . DIRECTORY_SEPARATOR);
});

it('resolves locale directory with adminhtml directory for admin area', function () {
    $designPackage = Mage::getSingleton('core/design_package');
    $designPackage->setArea('admin');

    $localeDir = $designPackage->getLocaleBaseDir([
        '_area' => 'admin',
        '_package' => 'default',
        '_theme' => 'default',
    ]);
    expect($localeDir)->toContain(DIRECTORY_SEPARATOR . 'adminhtml' . DIRECTORY_SEPARATOR);
});

it('resolves theme inheritance config through design directory mapping', function () {
    $fallback = new Mage_Core_Model_Design_Fallback();
    $scheme = $fallback->getFallbackScheme('admin', 'default', 'default');

    expect($scheme)->toBeArray();
    expect($scheme)->not->toBeEmpty();
});

it('preserves admin routers config after consolidation', function () {
    $config = Mage::getConfig();

    $routers = $config->getNode('admin/routers/adminhtml');
    expect($routers)->not->toBeFalse('Admin routers should exist under admin/routers');
    expect((string) $routers->use)->toBe('admin');
    expect((string) $routers->args->frontName)->not->toBeEmpty();
});

it('reads admin event config from the admin config node', function () {
    $config = Mage::getConfig();

    // getEventConfig reads from {area}/events — should work with 'admin'
    $eventConfig = $config->getEventConfig('admin', 'controller_action_predispatch');
    if ($eventConfig !== false && $eventConfig !== null) {
        expect($eventConfig)->toBeInstanceOf(Mage_Core_Model_Config_Element::class);
    }

    // 'adminhtml' area should have no events in the config (all migrated to 'admin')
    $adminhtmlEvents = $config->getNode('adminhtml/events');
    expect($adminhtmlEvents)->toBeFalse('No events should remain under adminhtml/');
});
