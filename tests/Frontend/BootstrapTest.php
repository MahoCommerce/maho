<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

it('can load Maho classes and path is set correctly', function () {
    // Test that Mage class is available via autoloader
    expect(class_exists('Mage'))->toBeTrue();
    expect(class_exists('Mage_Core_Model_App'))->toBeTrue();

    // Test that Maho root path is set correctly (should point to main Maho directory)
    expect(Mage::getRoot())->toBe(dirname(__DIR__, 2));
});
