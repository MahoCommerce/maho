<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

afterEach(function (): void {
    Mage::unregister('current_convert_profile');
});

it('escapes dataflow exception messages in the process log', function () {
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    Mage::register('current_convert_profile', Mage::getModel('dataflow/profile')->setId(1));
    $block = Mage::app()->getLayout()->createBlock('adminhtml/system_convert_profile_run');
    $block->setTemplate('system/convert/profile/process.phtml');
    $block->setExceptions([[
        'style' => '',
        'src' => '',
        'message' => 'Could not save file: <img src=x onerror=alert(1)>.',
        'position' => '',
    ]]);

    $html = $block->toHtml();

    expect($html)->not->toContain('<img src=x')
        ->and($html)->toContain('Could not save file: &lt;img src=x onerror=alert(1)&gt;.');
});
