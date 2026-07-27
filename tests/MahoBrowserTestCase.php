<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests;

use Tests\Browser\MahoServer;

/**
 * Base test case for browser tests.
 *
 * Skips the whole suite when Playwright isn't installed and boots the dev server the tests
 * navigate to. The server starts once per run and is stopped when the process ends, so tests
 * only care about the fixtures and config they set up themselves.
 */
abstract class MahoBrowserTestCase extends MahoFrontendTestCase
{
    protected function setUp(): void
    {
        if (!browserTestsReady()) {
            $this->markTestSkipped('Playwright is not installed');
        }

        parent::setUp();

        MahoServer::start();
    }
}
