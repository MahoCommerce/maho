<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;

uses()->group('browser');

afterAll(fn() => MahoServer::stop());

beforeEach(function () {
    if (!browserTestsReady()) {
        test()->markTestSkipped('Playwright is not installed');
    }
    MahoServer::start();
});

it('renders the storefront homepage in a real browser', function () {
    visit(MahoServer::baseUrl() . '/')
        ->assertNoSmoke();
});
