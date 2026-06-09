<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

// For frontend tests:
// uses(Tests\MahoFrontendTestCase::class);

// For backend tests:
// uses(Tests\MahoBackendTestCase::class);

// For installation tests:
// uses(Tests\MahoInstallTestCase::class);

/**
 * Whether the real-browser (Pest browser plugin / Playwright) toolchain is available.
 * The browser test suite runs as part of the normal pest run, but skips cleanly anywhere
 * Playwright isn't installed (e.g. CI matrix jobs that don't provision it), so it never
 * fails those environments. The plugin launches ./node_modules/.bin/playwright.
 */
function browserTestsReady(): bool
{
    return is_file(dirname(__DIR__) . '/node_modules/.bin/playwright');
}
