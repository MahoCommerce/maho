<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

// The native helper consumes real Fetch Response objects; only the transport is replaced.
const MAHO_FETCH_MEDIA_TYPE_SCENARIO = <<<'JS'
(async () => {
    const original = {fetch: window.fetch, showLoader: window.showLoader, hideLoader: window.hideLoader,
        setLocation: window.setLocation, formKey: window.FORM_KEY};
    const calls = [], loaders = [], redirects = [];
    let response;
    window.fetch = async (url, options) => {
        calls.push({url: url.toString(), method: options.method ?? null, body: options.body instanceof URLSearchParams
            ? Object.fromEntries(options.body) : options.body ?? null, marker: options.headers?.['X-Test'] ?? null});
        return response;
    };
    window.showLoader = area => loaders.push(['show', area]);
    window.hideLoader = () => loaders.push(['hide']);
    window.setLocation = url => redirects.push(url);
    window.FORM_KEY = 'fixture-key';
    const make = (body, type, status = 200) => new Response(body, {status, headers: type === null ? {} : {'Content-Type': type}});
    try {
        response = make('{"value":1}', 'application/json');
        const exact = await mahoFetch('https://example.test/exact', {loaderArea: false});
        response = make('{"value":2}', 'application/json; charset=utf-8');
        const parameterized = await mahoFetch('https://example.test/parameterized', {loaderArea: false});
        response = make('{"value":3}', ' \tApPlIcAtIoN/JsOn ; Charset=UTF-8\t');
        const mixed = await mahoFetch('https://example.test/mixed', {loaderArea: false});
        response = make('{"error":true,"message":"rejected"}', 'application/json; charset=utf-8');
        let rejected = null;
        try { await mahoFetch('https://example.test/error', {loaderArea: false}); } catch (error) { rejected = [error.name, error.message]; }
        response = make('{"error":true,"message":"as data"}', 'application/json; charset=utf-8');
        const asData = await mahoFetch('https://example.test/error-data', {loaderArea: false, rejectOnError: false});
        response = make('{"ajaxExpired":true,"ajaxRedirect":"/login"}', 'application/json; charset=utf-8');
        const expiry = await Promise.race([mahoFetch('https://example.test/expired', {loaderArea: false}).then(() => 'resolved'),
            new Promise(resolve => setTimeout(() => resolve('pending'), 30))]);
        response = make('{"value":4}', 'text/plain');
        const text = await mahoFetch('https://example.test/text', {loaderArea: false});
        response = make('{"value":5}', 'application/problem+json');
        const otherJson = await mahoFetch('https://example.test/other-json', {loaderArea: false});
        response = make('unavailable', 'text/plain', 503);
        let statusError = null;
        try { await mahoFetch('https://example.test/status', {loaderArea: false}); } catch (error) { statusError = [error.name, error.message]; }
        response = make('{"ok":true}', 'application/json; charset=utf-8');
        const body = new URLSearchParams({name: 'sample'});
        const posted = await mahoFetch('https://example.test/post?original=1', {method: 'POST', body, headers: {'X-Test': 'kept'}, loaderArea: 'area'});
        return {exact, parameterized, mixed, rejected, asData, expiry, redirects, text, otherJson, statusError, posted,
            post: calls.at(-1), loaders};
    } finally {
        window.fetch = original.fetch;
        window.showLoader = original.showLoader;
        window.hideLoader = original.hideLoader;
        window.setLocation = original.setLocation;
        if (original.formKey === undefined) delete window.FORM_KEY; else window.FORM_KEY = original.formKey;
    }
})()
JS;

it('recognises JSON media types with parameters while preserving fetch behaviour', function (): void {
    $page = waitForPageLoad(visit(MahoServer::baseUrl() . '/admin'), '#username');
    $result = $page->script(MAHO_FETCH_MEDIA_TYPE_SCENARIO);
    expect($result)->toBe([
        'exact' => ['value' => 1], 'parameterized' => ['value' => 2], 'mixed' => ['value' => 3],
        'rejected' => ['MahoError', 'rejected'], 'asData' => ['error' => true, 'message' => 'as data'],
        'expiry' => 'pending', 'redirects' => ['/login'], 'text' => '{"value":4}',
        'otherJson' => '{"value":5}', 'statusError' => ['MahoError', 'Server returned status 503'],
        'posted' => ['ok' => true], 'post' => ['url' => 'https://example.test/post?original=1&isAjax=true',
            'method' => 'POST', 'body' => ['name' => 'sample', 'form_key' => 'fixture-key'], 'marker' => 'kept'],
        'loaders' => [['show', 'area'], ['hide']],
    ]);
    $page->assertNoJavaScriptErrors();
});
