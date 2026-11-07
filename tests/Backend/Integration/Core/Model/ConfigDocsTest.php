<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('stores the docs folders of a module under the name of the module', function (): void {
    expect((string) Mage::getConfig()->getNode('global/docs/Mage_ConfigurableSwatches/user'))->toBe('docs/user');
    expect(Mage::getConfig()->getNode('global/docs/user'))->toBeFalse();
});

it('keeps the folders of two modules that declare the same audience', function (): void {
    $merged = new Mage_Core_Model_Config_Base('<config/>');
    $move = new ReflectionMethod(Mage_Core_Model_Config::class, '_moveDocsUnderModule');
    foreach (['Acme_One' => 'docs/one', 'Acme_Two' => 'docs/two'] as $module => $path) {
        $moduleConfig = new Mage_Core_Model_Config_Base("<config><global><docs><user>$path</user></docs></global></config>");
        $move->invoke(Mage::getConfig(), $moduleConfig, $module);
        $merged->extend($moduleConfig, true);
    }

    expect((string) $merged->getNode('global/docs/Acme_One/user'))->toBe('docs/one');
    expect((string) $merged->getNode('global/docs/Acme_Two/user'))->toBe('docs/two');
    expect($merged->getNode('global/docs/user'))->toBeFalse();
});
