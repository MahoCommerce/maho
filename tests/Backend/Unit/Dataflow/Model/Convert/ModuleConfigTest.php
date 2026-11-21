<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('loads the shipped Dataflow configuration for the catalog module', function (bool $resolvePackage): void {
    $options = Mage::getConfig()->getOptions();
    $codeDirectory = $options->getCodeDir();
    $workingDirectory = getcwd() ?: BP;

    if ($resolvePackage) {
        $options->setData('code_dir', 'app/code');
        chdir(__DIR__);
    }

    try {
        $file = Mage::getModuleDir('etc', 'Mage_Catalog') . '/convert.xml';
        expect(is_readable($file))->toBe(!$resolvePackage)
            ->and(new Mage_Catalog_Model_Convert()->getContainers()->hasItem('catalog_file'))->toBeTrue();
    } finally {
        chdir($workingDirectory);
        $options->setData('code_dir', $codeDirectory);
    }
})->with([
    'source checkout' => [false],
    'package resolution' => [true],
]);

it('allows a module without a convert.xml file', function (): void {
    expect(new Mage_Core_Model_Convert()->getProfile('example'))->toBeNull();
});
