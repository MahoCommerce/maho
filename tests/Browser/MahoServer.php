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
 * The Pest runner installs the test database with base_url http://127.0.0.1:<port>/
 * (PestTestRunner::testBaseUrl), and this serves the app on that exact host:port — so
 * there is no runtime base_url rewrite and every suite shares one configuration.
 * 127.0.0.1 is used because Chromium under Playwright ignores /etc/hosts.
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

        $port ??= (int) (getenv('MAHO_BROWSER_PORT') ?: 8901);
        self::$baseUrl = "http://127.0.0.1:{$port}";

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
