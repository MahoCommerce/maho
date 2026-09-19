<?php

/**
 * A validated outbound URL, pinned to the address that passed the check.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

final readonly class OutboundTarget
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public string $path,
        public ?string $query,
        public ?string $ip,
    ) {}

    public function isPinned(): bool
    {
        return $this->ip !== null;
    }

    public function requestTarget(): string
    {
        return $this->path . ($this->query === null ? '' : '?' . $this->query);
    }

    public function hostHeader(): string
    {
        $defaultPort = $this->scheme === 'https' ? 443 : 80;
        return $this->bracket($this->host) . ($this->port === $defaultPort ? '' : ':' . $this->port);
    }

    /**
     * The URL with the pinned address as authority, so a second DNS lookup cannot change the destination.
     */
    public function pinnedUrl(): string
    {
        if ($this->ip === null) {
            return $this->url;
        }
        $defaultPort = $this->scheme === 'https' ? 443 : 80;
        $authority = $this->bracket($this->ip) . ($this->port === $defaultPort ? '' : ':' . $this->port);
        return $this->scheme . '://' . $authority . $this->requestTarget();
    }

    public function socketAddress(): string
    {
        return ($this->scheme === 'https' ? 'ssl://' : 'tcp://') . $this->bracket($this->ip ?? $this->host) . ':' . $this->port;
    }

    /**
     * Options for stream_context_create(): the original host name goes into the Host header and TLS peer name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function streamContextOptions(int $timeout = 30): array
    {
        return [
            'http' => [
                'header' => 'Host: ' . $this->hostHeader() . "\r\n",
                'timeout' => $timeout,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => $this->sslOptions(),
        ];
    }

    /**
     * Options for a Symfony HttpClient request().
     *
     * @return array<string, mixed>
     */
    public function httpClientOptions(): array
    {
        $options = ['max_redirects' => 0];
        if ($this->ip !== null) {
            $options['resolve'] = [$this->host => $this->ip];
        }
        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function sslOptions(): array
    {
        return [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
            'peer_name' => $this->host,
        ];
    }

    private function bracket(string $address): string
    {
        return str_contains($address, ':') ? '[' . $address . ']' : $address;
    }
}
