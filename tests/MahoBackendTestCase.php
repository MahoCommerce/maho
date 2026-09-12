<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests;

use Mage;

/**
 * Base test case for admin/backend tests
 */
abstract class MahoBackendTestCase extends MahoTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Mage::register('isSecureArea', true);
    }
}
