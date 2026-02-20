<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('can load Maho admin classes and path is set correctly', function () {
    // Test that admin classes are available via autoloader
    expect(class_exists('Mage_Adminhtml_Helper_Data'))->toBeTrue();
    expect(class_exists('Mage_Admin_Model_Session'))->toBeTrue();

    // Test that Maho root path is set correctly (should point to main Maho directory)
    expect(Mage::getRoot())->toBe(dirname(__DIR__, 2));
});
