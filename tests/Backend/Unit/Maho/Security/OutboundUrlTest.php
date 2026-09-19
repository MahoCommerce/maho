<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Security\OutboundUrl;
use Maho\Security\OutboundUrlException;

function outboundUrl(array $records = []): OutboundUrl
{
    return new OutboundUrl(fn(string $host): array => $records[$host] ?? []);
}

describe('OutboundUrl', function (): void {
    it('rejects schemes other than http and https', function (string $url): void {
        expect(fn() => outboundUrl()->validate($url))->toThrow(OutboundUrlException::class);
    })->with(['ftp://example.com/file', 'file:///etc/passwd', 'gopher://example.com', '//example.com', 'example.com/path']);

    it('rejects private, loopback, link-local and reserved literals', function (string $url): void {
        expect(fn() => outboundUrl()->validate($url))->toThrow(OutboundUrlException::class, 'private or reserved');
    })->with([
        'http://127.0.0.1/',
        'http://127.1.2.3:8080/x',
        'http://10.0.0.5/',
        'http://172.16.4.4/',
        'http://192.168.1.1/',
        'http://169.254.169.254/latest/meta-data/',
        'http://0.0.0.0/',
        'http://100.64.0.1/',
        'http://224.0.0.1/',
        'http://[::1]/',
        'http://[::]/',
        'http://[fd00::1]/',
        'http://[fe80::1]/',
        'http://[::ffff:127.0.0.1]/',
        'http://[64:ff9b::7f00:1]/',
        'http://[2002:7f00:1::1]/',
    ]);

    it('rejects a host that resolves to a non-public address', function (): void {
        $helper = outboundUrl(['internal.example' => ['127.0.0.1'], 'dual.example' => ['93.184.216.34', '10.0.0.1']]);
        expect(fn() => $helper->validate('http://internal.example/'))->toThrow(OutboundUrlException::class, 'private or reserved');
        expect(fn() => $helper->validate('http://dual.example/'))->toThrow(OutboundUrlException::class, 'private or reserved');
    });

    it('rejects a host that does not resolve', function (): void {
        expect(fn() => outboundUrl()->validate('http://nowhere.example/'))->toThrow(OutboundUrlException::class, 'resolved');
    });

    it('rejects a URL without a host', function (): void {
        expect(fn() => outboundUrl()->validate('http:///path'))->toThrow(OutboundUrlException::class);
    });

    it('accepts a public host and pins the resolved address', function (): void {
        $target = outboundUrl(['cdn.example' => ['93.184.216.34']])->validate('https://cdn.example/files/a.zip?v=1');
        expect($target->host)->toBe('cdn.example')
            ->and($target->ip)->toBe('93.184.216.34')
            ->and($target->port)->toBe(443)
            ->and($target->pinnedUrl())->toBe('https://93.184.216.34/files/a.zip?v=1')
            ->and($target->socketAddress())->toBe('ssl://93.184.216.34:443')
            ->and($target->httpClientOptions()['resolve'])->toBe(['cdn.example' => '93.184.216.34'])
            ->and($target->httpClientOptions()['max_redirects'])->toBe(0)
            ->and($target->streamContextOptions()['ssl']['peer_name'])->toBe('cdn.example')
            ->and($target->streamContextOptions()['http']['follow_location'])->toBe(0)
            ->and($target->streamContextOptions()['http']['header'])->toContain('Host: cdn.example');
    });

    it('accepts a public IPv6 host and brackets the pinned address', function (): void {
        $target = outboundUrl(['v6.example' => ['2606:2800:220:1:248:1893:25c8:1946']])->validate('http://v6.example:8080/x');
        expect($target->pinnedUrl())->toBe('http://[2606:2800:220:1:248:1893:25c8:1946]:8080/x')
            ->and($target->socketAddress())->toBe('tcp://[2606:2800:220:1:248:1893:25c8:1946]:8080')
            ->and($target->hostHeader())->toBe('v6.example:8080');
    });

    it('accepts a public IP literal without resolution', function (): void {
        $target = outboundUrl()->validate('http://93.184.216.34/a');
        expect($target->ip)->toBe('93.184.216.34');
    });

    it('skips the address check for an allowed prefix', function (): void {
        $target = outboundUrl()->validate('http://intranet.example/files/a.zip', ["http://intranet.example/files/\n", '']);
        expect($target->ip)->toBeNull()
            ->and($target->pinnedUrl())->toBe('http://intranet.example/files/a.zip')
            ->and($target->socketAddress())->toBe('tcp://intranet.example:80')
            ->and($target->httpClientOptions())->not->toHaveKey('resolve');
        expect(fn() => outboundUrl()->validate('http://intranet.example/other', ['http://intranet.example/files/']))
            ->toThrow(OutboundUrlException::class);
    });

    it('classifies addresses', function (): void {
        expect(OutboundUrl::isPublicAddress('8.8.8.8'))->toBeTrue()
            ->and(OutboundUrl::isPublicAddress('2001:4860:4860::8888'))->toBeTrue()
            ->and(OutboundUrl::isPublicAddress('192.168.0.1'))->toBeFalse()
            ->and(OutboundUrl::isPublicAddress('fc00::1'))->toBeFalse()
            ->and(OutboundUrl::isPublicAddress('not-an-ip'))->toBeFalse();
    });
});
