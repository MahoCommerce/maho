<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('finds a skin file of the frontend theme of a store view from the admin area', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    $design = Mage::getModel('core/design_package')->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);

    $path = $design->getStoreSkinFilename('images/logo.svg', $store);

    expect($path)->toBeString()
        ->toContain('/skin/frontend/')
        ->toEndWith('/images/logo.svg')
        ->and(file_exists($path))->toBeTrue();
});
