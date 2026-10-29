<?php

/**
 * Validates a URL before the server fetches it, so a stored or submitted URL cannot reach internal hosts.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

use Uri\WhatWg\Url;

final class OutboundUrl
{
    public const SCHEMES = ['http', 'https'];

    /**
     * Special-purpose ranges that FILTER_FLAG_NO_RES_RANGE accepts. A host on a NAT64,
     * a cloud or a lab network can route them to an internal service.
     */
    private const NON_PUBLIC_RANGES = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/3',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        'fec0::/10',
        'ff00::/8',
    ];

    /** @var \Closure(string): list<string> */
    private \Closure $resolver;

    /**
     * @param \Closure(string): list<string>|null $resolver returns every A and AAAA address of a host name
     */
    public function __construct(?\Closure $resolver = null)
    {
        $this->resolver = $resolver ?? self::resolveHost(...);
    }

    /**
     * Every resolved address must be public, unless an allowed prefix names the same scheme, host and port
     * and the URL path starts with the prefix path.
     *
     * @param list<string> $allowedPrefixes
     * @throws OutboundUrlException
     */
    public function validate(string $url, array $allowedPrefixes = []): OutboundTarget
    {
        $uri = self::parse($url);
        $url = $uri->toAsciiString();
        $scheme = $uri->getScheme();
        $host = trim((string) $uri->getAsciiHost(), '[]');
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
        $path = $uri->getPath();
        $query = $uri->getQuery();

        if (self::matchesPrefix($scheme, $host, $port, $path, $allowedPrefixes)) {
            return new OutboundTarget($url, $scheme, $host, $port, $path, $query, null);
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);
        if ($addresses === []) {
            throw new OutboundUrlException('The URL host cannot be resolved.');
        }
        foreach ($addresses as $address) {
            if (!self::isPublicAddress($address)) {
                throw new OutboundUrlException('The URL host resolves to a private or reserved address.');
            }
        }

        return new OutboundTarget($url, $scheme, $host, $port, $path, $query, $addresses[0]);
    }

    /**
     * Read the URL as a browser does, with no DNS lookup.
     *
     * @throws OutboundUrlException
     */
    public static function parse(string $url): Url
    {
        // The parser removes a tab, a CR or an LF with no error, which changes the URL
        $uri = preg_match('/[\x00-\x1F\x7F]/', $url) ? null : Url::parse($url);
        if ($uri === null) {
            throw new OutboundUrlException('The URL is malformed.');
        }
        if (!in_array($uri->getScheme(), self::SCHEMES, true)) {
            throw new OutboundUrlException('The URL must use the http or https scheme.');
        }
        return $uri;
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        $packed = inet_pton($address);
        if ($packed === false) {
            return false;
        }
        foreach (self::NON_PUBLIC_RANGES as $range) {
            if (self::isInRange($packed, $range)) {
                return false;
            }
        }
        if (strlen($packed) === 4) {
            return true;
        }
        // IPv4-compatible (::/96), NAT64 (64:ff9b::/96) and 6to4 (2002::/16) embed an IPv4 address that must be public too
        if (str_starts_with($packed, str_repeat("\x00", 12)) || str_starts_with($packed, "\x00\x64\xff\x9b" . str_repeat("\x00", 8))) {
            return self::isPublicAddress(inet_ntop(substr($packed, 12)) ?: '');
        }
        if (str_starts_with($packed, "\x20\x02")) {
            return self::isPublicAddress(inet_ntop(substr($packed, 2, 4)) ?: '');
        }
        return true;
    }

    private static function isInRange(string $packed, string $range): bool
    {
        [$network, $bits] = explode('/', $range);
        $packedNetwork = inet_pton($network);
        if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packed)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($packed, 0, $bytes) !== substr($packedNetwork, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($packed[$bytes]) & $mask) === (ord($packedNetwork[$bytes]) & $mask);
    }

    /**
     * @param list<string> $prefixes
     */
    private static function matchesPrefix(string $scheme, string $host, int $port, string $path, array $prefixes): bool
    {
        // Some servers decode %2F or %5C to a slash, or remove a ";" parameter, before they remove "..", so the path can leave the prefix
        if (preg_match('/%(2F|5C)|;/i', $path)) {
            return false;
        }
        foreach ($prefixes as $prefix) {
            $uri = Url::parse(trim($prefix));
            $prefixScheme = $uri?->getScheme();
            $prefixHost = trim($uri?->getAsciiHost() ?? '', '[]');
            if ($prefixScheme === null || $prefixHost === '') {
                continue;
            }
            $prefixPort = $uri->getPort() ?? ($prefixScheme === 'https' ? 443 : 80);
            $prefixPath = $uri->getPath();
            if ($prefixScheme === $scheme
                && $prefixHost === $host
                && $prefixPort === $port
                && str_starts_with($path, $prefixPath)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string>
     */
    private static function resolveHost(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }
        return array_values(array_unique($addresses));
    }
}
