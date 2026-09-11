<?php

/**
 * Validates a URL before the server fetches it, so a stored or submitted URL cannot reach internal hosts.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

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
     * Every resolved address must be public, or the URL must start with one of the allowed prefixes.
     *
     * @param list<string> $allowedPrefixes
     * @throws OutboundUrlException
     */
    public function validate(string $url, array $allowedPrefixes = []): OutboundTarget
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new OutboundUrlException('The URL is malformed.');
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, self::SCHEMES, true)) {
            throw new OutboundUrlException('The URL must use the http or https scheme.');
        }
        $host = trim($parts['host'] ?? '', '[]');
        if ($host === '') {
            throw new OutboundUrlException('The URL has no host.');
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        $query = $parts['query'] ?? null;

        if (self::matchesPrefix($url, $allowedPrefixes)) {
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
    private static function matchesPrefix(string $url, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $prefix = trim($prefix);
            if ($prefix !== '' && str_starts_with(strtolower($url), strtolower($prefix))) {
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
