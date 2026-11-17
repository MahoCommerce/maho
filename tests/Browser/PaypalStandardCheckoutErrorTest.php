<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

it('shows the server text of a failed PayPal request and hides a status code and an SDK error', function (): void {
    $page = visit(MahoServer::baseUrl() . '/');
    $page->script(<<<'JS'
        new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/js/maho/paypal/standard-checkout.js';
            script.onload = () => resolve(true);
            script.onerror = () => reject(new Error('Could not load standard-checkout.js'));
            document.head.append(script);
        })
        JS);

    $result = $page->script(<<<'JS'
        (() => {
            const div = document.createElement('div');
            document.body.append(div);
            const checkout = new MahoPaypalStandardCheckout(div);
            const show = (err) => { checkout.handleError(err); return div.querySelector('.paypal-standard-errors').textContent; };
            return {
                server: show(new MahoError('Invalid form key. Please refresh the page.')),
                status: show(new MahoError('Server returned status %s', 503)),
                sdk: show(new Error('unable to open popup')),
            };
        })()
        JS);

    $generic = 'We could not complete your PayPal payment. Please try again, or choose another payment method.';
    expect($result)->toBe(['server' => 'Invalid form key. Please refresh the page.', 'status' => $generic, 'sdk' => $generic]);
});
