<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The registered callback URL is an allowlist entry, not a string prefix. A callback
 * is accepted only when its scheme, host and effective port match exactly and its path
 * continues the registered path at a segment boundary.
 */
function isCallbackAllowed(string $callback, string $registered = 'https://legit.com/oauth/cb'): bool
{
    return Mage::helper('oauth')->isCallbackUrlOnAllowlist($callback, $registered);
}

it('accepts a callback that extends the registered path at a segment boundary', function (string $callback) {
    expect(isCallbackAllowed($callback))->toBeTrue();
})->with([
    'exact' => 'https://legit.com/oauth/cb',
    'trailing slash' => 'https://legit.com/oauth/cb/',
    'sub path' => 'https://legit.com/oauth/cb/done',
    'query' => 'https://legit.com/oauth/cb?state=1',
    'explicit default port' => 'https://legit.com:443/oauth/cb',
    'upper case host' => 'https://LEGIT.com/oauth/cb',
]);

it('rejects a callback whose authority only starts with the registered one', function (string $callback) {
    expect(isCallbackAllowed($callback))->toBeFalse();
})->with([
    'host suffix' => 'https://legit.com.evil.com/oauth/cb',
    'userinfo' => 'https://legit.com@evil.com/oauth/cb',
    'userinfo with password' => 'https://legit.com:x@evil.com/oauth/cb',
    'other port' => 'https://legit.com:8443/oauth/cb',
    'other scheme' => 'http://legit.com/oauth/cb',
    'other host' => 'https://evil.com/oauth/cb',
    'path prefix without boundary' => 'https://legit.com/oauth/cbx',
    'other path' => 'https://legit.com/evil',
    'dot segment' => 'https://legit.com/oauth/cb/../evil',
    'backslash' => 'https://legit.com\\evil.com/oauth/cb',
    'scheme relative' => '//legit.com/oauth/cb',
    'empty' => '',
]);

it('treats a registered path with a trailing slash as a directory', function () {
    expect(isCallbackAllowed('https://legit.com/oauth/cb/x', 'https://legit.com/oauth/cb/'))->toBeTrue()
        ->and(isCallbackAllowed('https://legit.com/oauth/cbx', 'https://legit.com/oauth/cb/'))->toBeFalse();
});

it('accepts a registered host without a path for any path on that host', function () {
    expect(isCallbackAllowed('https://legit.com/any/where', 'https://legit.com'))->toBeTrue()
        ->and(isCallbackAllowed('https://legit.com.evil.com/', 'https://legit.com'))->toBeFalse();
});

it('keeps a registered query string', function () {
    expect(isCallbackAllowed('https://legit.com/cb?app=1&state=x', 'https://legit.com/cb?app=1'))->toBeTrue()
        ->and(isCallbackAllowed('https://legit.com/cb?app=10', 'https://legit.com/cb?app=1'))->toBeFalse()
        ->and(isCallbackAllowed('https://legit.com/cb', 'https://legit.com/cb?app=1'))->toBeFalse();
});

it('requires full equality when the registered URL has no authority', function () {
    expect(isCallbackAllowed('myapp:callback', 'myapp:callback'))->toBeTrue()
        ->and(isCallbackAllowed('myapp:callback/x', 'myapp:callback'))->toBeFalse()
        ->and(isCallbackAllowed('myapp:callbackx', 'myapp:callback'))->toBeFalse();
});

it('rejects a registered URL that carries userinfo', function () {
    expect(isCallbackAllowed('https://user@legit.com/cb', 'https://user@legit.com/cb'))->toBeFalse();
});
