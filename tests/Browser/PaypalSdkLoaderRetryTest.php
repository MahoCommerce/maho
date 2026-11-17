<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

it('loads the PayPal SDK on a second call after the first call failed', function (): void {
    $page = visit(MahoServer::baseUrl() . '/');
    $page->script(<<<'JS'
        new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/js/maho/paypal/sdk-loader.js';
            script.onload = () => resolve(true);
            script.onerror = () => reject(new Error('Could not load sdk-loader.js'));
            document.head.append(script);
        })
        JS);

    // window.paypal makes the loader skip the PayPal script, and the mahoFetch stub sends no request
    $result = $page->script(<<<'JS'
        (async () => {
            window.paypal = {createInstance: async () => 'instance'};
            let calls = 0;
            window.mahoFetch = async () => ++calls === 1 ? {success: false} : {success: true, client_token: 't'};
            let first = null;
            try { await MahoPaypalSdk.init('unused', '/paypal/checkout/clientToken'); } catch (e) { first = e.message; }
            let second;
            try { second = await MahoPaypalSdk.init('unused', '/paypal/checkout/clientToken'); } catch (e) { second = e.message; }
            return {first, second, calls};
        })()
        JS);

    expect($result)->toBe(['first' => 'Failed to get client token', 'second' => 'instance', 'calls' => 2]);
    $page->assertNoJavaScriptErrors();
});
