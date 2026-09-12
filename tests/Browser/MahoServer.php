<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Browser;

use Symfony\Component\Process\Process;

/**
 * Resolves the one HTTP server the Api and Browser suites share, starting it only when
 * nobody did already (a bare `vendor/bin/pest`; the runner and CI start it themselves).
 *
 * It serves the exact host:port the store was installed with, so base_url is never
 * rewritten. Both suites depend on that: redirect_to_base bounces a request whose host
 * differs, and JwtService::getIssuer() derives the issuer from base_url, so a token signed
 * against another origin 401s.
 *
 * The host comes from MAHO_BROWSER_HOST, the runner's real IP on CI: Playwright's Chromium
 * ignores /etc/hosts and is unreliable with bare 127.0.0.1 there.
 */
final class MahoServer
{
    private static ?Process $process = null;
    private static string $baseUrl = '';
    private static bool $stopRegistered = false;

    public static function start(?int $port = null): string
    {
        if (self::$baseUrl !== '' && (self::$process === null || self::$process->isRunning())) {
            return self::$baseUrl;
        }

        // Address host (what the browser navigates to) vs bind host (what the server binds).
        // They differ only in the local default: navigate `localhost`, bind 127.0.0.1.
        $envHost = getenv('MAHO_BROWSER_HOST') ?: '';
        $addressHost = $envHost !== '' ? $envHost : 'localhost';
        $bindHost = $envHost !== '' ? $envHost : '127.0.0.1';
        $port ??= (int) (getenv('MAHO_BROWSER_PORT') ?: 8901);
        self::$baseUrl = "http://{$addressHost}:{$port}";

        // Already served for the Api suite: reuse it rather than fight for the port.
        if (self::isListening($bindHost, $port)) {
            return self::$baseUrl;
        }

        // One server for the whole run: every test file starts it, and it lives until the
        // process ends rather than being torn down and rebuilt between files.
        if (!self::$stopRegistered) {
            register_shutdown_function(self::stop(...));
            self::$stopRegistered = true;
        }

        // router.php, not `./maho serve`, which has no router: without it the /api/*
        // rewrites public/.htaccess performs in production are missing. Workers because
        // the built-in server is single-threaded and parallel asset requests would stall.
        self::$process = new Process(
            [
                PHP_BINARY,
                '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.validate_timestamps=1',
                '-d', 'opcache.revalidate_freq=0',
                '-S', "{$bindHost}:{$port}",
                '-t', 'public',
                __DIR__ . '/../router.php',
            ],
            null,
            [
                'PHP_CLI_SERVER_WORKERS' => (string) (getenv('MAHO_BROWSER_WORKERS') ?: 8),
                'MAHO_GRAPHQL_INTROSPECTION' => '1',
            ],
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

    private static function isListening(string $host, int $port): bool
    {
        $conn = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($conn === false) {
            return false;
        }
        fclose($conn);
        return true;
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
