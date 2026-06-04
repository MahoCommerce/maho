<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Browser;

use Symfony\Component\Process\Process;

/**
 * Boots `./maho serve` for browser tests.
 *
 * The Pest runner installs the test database with base_url http://<host>:<port>/
 * (PestTestRunner::testBaseUrl), and this serves the app on that exact host:port — so
 * there is no runtime base_url rewrite and every suite shares one configuration. The host
 * is `localhost`: Playwright's Chromium ignores /etc/hosts and, on CI runners, even fails
 * to resolve the bare loopback IP 127.0.0.1 (ERR_NAME_NOT_RESOLVED), but it resolves
 * `localhost` via its built-in rule. `./maho serve` binds 127.0.0.1, which localhost maps
 * to; the port must match base_url or redirect_to_base bounces to an unserved origin.
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

        $host = getenv('MAHO_BROWSER_HOST') ?: 'localhost';
        $port ??= (int) (getenv('MAHO_BROWSER_PORT') ?: 8901);
        self::$baseUrl = "http://{$host}:{$port}";

        // The PHP built-in server is single-threaded; a browser's parallel asset
        // requests would serialize and stall. Spawn workers so requests run concurrently.
        self::$process = new Process(
            ['./maho', 'serve', (string) $port],
            null,
            ['PHP_CLI_SERVER_WORKERS' => (string) (getenv('MAHO_BROWSER_WORKERS') ?: 8)],
        );
        self::$process->setTimeout(null);
        self::$process->start();

        self::waitUntilReady($port);

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

    private static function waitUntilReady(int $port, int $timeoutSeconds = 30): void
    {
        $deadline = time() + $timeoutSeconds;
        while (time() < $deadline) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(200_000);
        }
        throw new \RuntimeException("Maho dev server did not start on port {$port} within {$timeoutSeconds}s");
    }
}
