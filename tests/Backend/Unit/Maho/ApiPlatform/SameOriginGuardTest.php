<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

use Maho\ApiPlatform\Security\SameOriginGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(Tests\MahoBackendTestCase::class);

it('normalizes the scheme, host and default ports of an origin', function (): void {
    expect(SameOriginGuard::originOf('HTTP://LOCALHOST:80/path'))->toBe('http://localhost')
        ->and(SameOriginGuard::originOf('https://EXAMPLE.COM:443/'))->toBe('https://example.com')
        ->and(SameOriginGuard::originOf('https://example.com:8443/path'))->toBe('https://example.com:8443');
});

it('accepts a browser origin with the default port omitted', function (): void {
    $store = Mage::app()->getStore();
    $secure = (string) Mage::getStoreConfig('web/secure/base_url');
    $unsecure = (string) Mage::getStoreConfig('web/unsecure/base_url');

    try {
        $store->setConfig('web/secure/base_url', 'https://localhost:443/');
        $store->setConfig('web/unsecure/base_url', 'http://localhost:80/');
        $request = Request::create('http://localhost/api/admin/graphql', server: [
            'HTTP_ORIGIN' => 'http://localhost',
        ]);

        expect(fn() => SameOriginGuard::assert($request))->not->toThrow(AccessDeniedHttpException::class);
        $otherPort = Request::create('http://localhost/api/admin/graphql', server: [
            'HTTP_ORIGIN' => 'http://localhost:8443',
        ]);

        expect(fn() => SameOriginGuard::assert($otherPort))->toThrow(AccessDeniedHttpException::class);
    } finally {
        $store->setConfig('web/secure/base_url', $secure);
        $store->setConfig('web/unsecure/base_url', $unsecure);
    }
});

it('normalizes the Referer when the Origin header is absent', function (): void {
    $store = Mage::app()->getStore();
    $secure = (string) Mage::getStoreConfig('web/secure/base_url');
    $unsecure = (string) Mage::getStoreConfig('web/unsecure/base_url');

    try {
        $store->setConfig('web/secure/base_url', 'https://example.test:443/');
        $store->setConfig('web/unsecure/base_url', 'https://example.test:443/');
        $request = Request::create('https://example.test/api/admin/graphql', server: [
            'HTTP_REFERER' => 'HTTPS://EXAMPLE.TEST:443/path?query=1',
        ]);

        expect(fn() => SameOriginGuard::assert($request))->not->toThrow(AccessDeniedHttpException::class);
    } finally {
        $store->setConfig('web/secure/base_url', $secure);
        $store->setConfig('web/unsecure/base_url', $unsecure);
    }
});
