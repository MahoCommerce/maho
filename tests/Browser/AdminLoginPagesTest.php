<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The admin pages outside the admin frame: login and forgot password.
 *
 * The forgot password page loads form.js but not events.js. form.js called
 * varienGlobalEvents?.attachEventHandler() at load, and optional chaining does not
 * protect a name that is not declared, so the page threw a ReferenceError.
 */

it('loads the forgot password page without a JavaScript error', function () {
    $page = visit(MahoServer::baseUrl() . '/admin/index/forgotpassword/');

    waitForPageLoad($page, '#email')->assertNoJavaScriptErrors();
});

it('validates the forgot password form without a JavaScript error', function () {
    $page = visit(MahoServer::baseUrl() . '/admin/index/forgotpassword/');
    waitForPageLoad($page, '#email')->click('button[type="submit"]');

    $page->assertVisible('.validation-advice')->assertNoJavaScriptErrors();
});

it('puts the forgot password link on its own line below the login buttons', function () {
    $page = waitForPageLoad(visit(MahoServer::baseUrl() . '/admin'), '#passkey-login-button');

    // A long translation must wrap inside the card, never squeeze beside the buttons.
    $gap = $page->script(<<<'JS'
        (() => {
            const link = document.querySelector('#step1 a[href*="forgotpassword"]').getBoundingClientRect();
            const button = document.getElementById('passkey-login-button').getBoundingClientRect();
            return link.top - button.bottom;
        })()
        JS);

    expect($gap)->toBeGreaterThanOrEqual(0);
    $page->assertNoJavaScriptErrors();
});
