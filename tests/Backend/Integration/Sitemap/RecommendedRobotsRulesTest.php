<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('restores the shipped rules when the file needs package resolution', function (): void {
    $block = new Mage_Sitemap_Block_Adminhtml_System_Config_Baserules();
    $shippedRules = $block->getRecommendedRules();
    expect($shippedRules)->not->toBeEmpty();

    $options = Mage::getConfig()->getOptions();
    $codeDirectory = $options->getCodeDir();
    $workingDirectory = getcwd() ?: BP;
    $options->setData('code_dir', 'app/code');
    chdir(__DIR__);
    try {
        $path = Mage::getModuleDir('etc', 'Mage_Sitemap') . DS . 'config.xml';
        expect(is_file($path))->toBeFalse()
            ->and($block->getRecommendedRules())->toBe($shippedRules);
    } finally {
        chdir($workingDirectory);
        $options->setData('code_dir', $codeDirectory);
    }
});

it('returns empty rules when the module configuration file is missing', function (): void {
    $options = Mage::getConfig()->getOptions();
    $codeDirectory = $options->getCodeDir();
    $options->setData('code_dir', __DIR__);
    try {
        expect(new Mage_Sitemap_Block_Adminhtml_System_Config_Baserules()->getRecommendedRules())->toBe('');
    } finally {
        $options->setData('code_dir', $codeDirectory);
    }
});
