<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoInstallTestCase::class);

it('can load Maho install classes and path is set correctly', function () {
    // Test that install classes are available via autoloader
    expect(class_exists('Mage_Install_Model_Installer'))->toBeTrue();
    expect(class_exists('Mage_Install_Controller_Router_Install'))->toBeTrue();

    // Test that Maho root path is set correctly (should point to main Maho directory)
    expect(Mage::getRoot())->toBe(dirname(__DIR__, 2));
});
