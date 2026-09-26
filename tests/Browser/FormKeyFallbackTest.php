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
 * js.js adds the form key to a POST form that has none, so an old theme template keeps
 * working. It must never add the key to a form that posts to another site.
 */

/** Submit a new POST form without a form key and tell if it carries one at submit time. */
function formKeyFallbackAdded(object $page, string $action): bool
{
    return (bool) $page->page()->evaluate('(action) => {
        const form = document.createElement("form");
        form.method = "post";
        form.action = action;
        document.body.appendChild(form);
        form.addEventListener("submit", (event) => event.preventDefault());
        form.requestSubmit();
        return form.querySelector("input[name=form_key]") !== null;
    }', $action);
}

it('adds the form key to a POST form that posts to this site', function () {
    $page = visit(MahoServer::baseUrl() . '/');
    waitForPageLoad($page, 'body:visible');

    expect(formKeyFallbackAdded($page, MahoServer::baseUrl() . '/checkout/cart/add'))->toBeTrue();
});

it('adds the form key when a script calls form.submit(), which fires no submit event', function () {
    $page = visit(MahoServer::baseUrl() . '/');
    waitForPageLoad($page, 'body:visible');

    $added = $page->page()->evaluate('(action) => {
        const frame = document.createElement("iframe");
        frame.name = "form-key-target";
        document.body.appendChild(frame);
        const form = document.createElement("form");
        form.method = "post";
        form.action = action;
        form.target = "form-key-target";
        document.body.appendChild(form);
        form.submit();
        return form.querySelector("input[name=form_key]") !== null;
    }', MahoServer::baseUrl() . '/checkout/cart/add');

    expect($added)->toBeTrue();
});

it('adds no form key to a POST form that posts to another site', function () {
    $page = visit(MahoServer::baseUrl() . '/');
    waitForPageLoad($page, 'body:visible');

    expect(formKeyFallbackAdded($page, 'https://example.com/checkout'))->toBeFalse();
});
