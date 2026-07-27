<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

it('renders the storefront homepage in a real browser', function () {
    $page = visit(MahoServer::baseUrl() . '/');

    // assertNoSmoke reads once, so on a half-built page it passes as readily as it fails.
    waitForPageLoad($page, 'body:visible')->assertNoSmoke();
});
