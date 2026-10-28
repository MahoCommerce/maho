<?php

/**
 * Validates a URL before the server fetches it, so a stored or submitted URL cannot reach internal hosts.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

use Uri\Rfc3986\Uri;

final class OutboundUrl
{
    public const SCHEMES = ['http', 'https'];

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
        $scheme = $uri->getScheme();
        $host = trim($uri->getHost(), '[]');
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
        $path = $uri->getPath() ?: '/';
        $query = $uri->getRawQuery();

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
     * Check the form of the URL, with no DNS lookup.
     *
     * @throws OutboundUrlException
     */
    public static function parse(string $url): Uri
    {
        $uri = Uri::parse($url);
        if ($uri === null || $uri->getPort() > 65535) {
            throw new OutboundUrlException('The URL is malformed.');
        }
        if (!in_array($uri->getScheme(), self::SCHEMES, true)) {
            throw new OutboundUrlException('The URL must use the http or https scheme.');
        }
        if (trim($uri->getHost() ?? '', '[]') === '') {
            throw new OutboundUrlException('The URL has no host.');
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
        if (strlen($packed) === 4) {
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            // 100.64.0.0/10 shared address space, 224.0.0.0/4 multicast and everything above
            return $first < 224 && !($first === 100 && ($second & 0xC0) === 0x40);
        }
        // NAT64 (64:ff9b::/96) and 6to4 (2002::/16) embed an IPv4 address that must be public too
        if (str_starts_with($packed, "\x00\x64\xff\x9b" . str_repeat("\x00", 8))) {
            return self::isPublicAddress(inet_ntop(substr($packed, 12)) ?: '');
        }
        if (str_starts_with($packed, "\x20\x02")) {
            return self::isPublicAddress(inet_ntop(substr($packed, 2, 4)) ?: '');
        }
        return ord($packed[0]) !== 0xff;
    }

    /**
     * @param list<string> $prefixes
     */
    private static function matchesPrefix(string $scheme, string $host, int $port, string $path, array $prefixes): bool
    {
        // A server that decodes %2F or %5C to a slash before it removes dot segments can leave the prefix path
        if (preg_match('/%(2F|5C)/i', $path)) {
            return false;
        }
        foreach ($prefixes as $prefix) {
            $uri = Uri::parse(trim($prefix));
            $prefixScheme = $uri?->getScheme();
            $prefixHost = trim($uri?->getHost() ?? '', '[]');
            if ($prefixScheme === null || $prefixHost === '') {
                continue;
            }
            $prefixPort = $uri->getPort() ?? ($prefixScheme === 'https' ? 443 : 80);
            $prefixPath = $uri->getPath() ?: '/';
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
