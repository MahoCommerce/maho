<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('creates the dashboard tab block from its layout handle', function (string $tab, string $class) {
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');
    $layout = Mage::getModel('core/layout');
    $layout->setArea('adminhtml');
    $layout->getUpdate()->load("adminhtml_dashboard_$tab");
    $layout->generateXml()->generateBlocks();

    expect($layout->getBlock("adminhtml.dashboard.tab.$tab"))->toBeInstanceOf($class);
})->with([
    'Top pages' => ['toppages', Mage_Log_Block_Dashboard_TopPages::class],
    'Entry/exit' => ['entryexit', Mage_Log_Block_Dashboard_EntryExit::class],
]);
