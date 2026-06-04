<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
