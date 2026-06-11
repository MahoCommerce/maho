<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

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
