<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Browser;

use Symfony\Component\Process\Process;

/**
 * Boots `./maho serve` for browser tests.
 *
 * The app is installed with base_url http://<host>:<port>/ and served on that exact
 * host:port (no runtime base_url rewrite), so every suite shares one configuration. The host
 * comes from MAHO_BROWSER_HOST: CI sets it to the runner's real IP (detected before
 * install), so the browser hits a routable address and sidesteps every loopback caveat
 * (Playwright's Chromium ignores /etc/hosts and is unreliable with bare 127.0.0.1 on CI).
 * Locally it defaults to `localhost`, served on 127.0.0.1 which localhost maps to. The
 * server binds the same host it's addressed by; the port must match base_url or
 * redirect_to_base bounces the browser to an unserved origin.
 */
final class MahoServer
{
    private static ?Process $process = null;
    private static string $baseUrl = '';

    public static function start(?int $port = null): string
    {
        if (self::$process && self::$process->isRunning()) {
            return self::$baseUrl;
        }

        // Address host (what the browser navigates to) vs bind host (what the server binds).
        // They differ only in the local default: navigate `localhost`, bind 127.0.0.1.
        $envHost = getenv('MAHO_BROWSER_HOST') ?: '';
        $addressHost = $envHost !== '' ? $envHost : 'localhost';
        $bindHost = $envHost !== '' ? $envHost : '127.0.0.1';
        $port ??= (int) (getenv('MAHO_BROWSER_PORT') ?: 8901);
        self::$baseUrl = "http://{$addressHost}:{$port}";

        // The PHP built-in server is single-threaded; a browser's parallel asset
        // requests would serialize and stall. Spawn workers so requests run concurrently.
        self::$process = new Process(
            ['./maho', 'serve', (string) $port, '--host', $bindHost],
            null,
            ['PHP_CLI_SERVER_WORKERS' => (string) (getenv('MAHO_BROWSER_WORKERS') ?: 8)],
        );
        self::$process->setTimeout(null);
        self::$process->start();

        self::waitUntilReady($bindHost, $port);

        return self::$baseUrl;
    }

    public static function baseUrl(): string
    {
        return self::$baseUrl !== '' ? self::$baseUrl : self::start();
    }

    public static function stop(): void
    {
        self::$process?->stop(3);
        self::$process = null;
    }

    private static function waitUntilReady(string $host, int $port, int $timeoutSeconds = 30): void
    {
        $deadline = time() + $timeoutSeconds;
        while (time() < $deadline) {
            $conn = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(200_000);
        }
        throw new \RuntimeException("Maho dev server did not start on {$host}:{$port} within {$timeoutSeconds}s");
    }
}
