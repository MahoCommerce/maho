<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tests;

use Mage;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for admin/backend tests
 */
abstract class MahoBackendTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set the correct Maho root path and initialize Maho for backend
        $this->setMahoRoot();
        Mage::register('isSecureArea', true);
        Mage::app();
    }

    protected function setMahoRoot(): void
    {
        Mage::setRoot(dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        // Reset Maho state and restore error handlers
        Mage::reset();
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }
}
