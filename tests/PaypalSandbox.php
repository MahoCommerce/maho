<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests;

/**
 * Loads PayPal sandbox credentials for E2E tests and applies them to the test store.
 *
 * Source of truth is the environment (CI secrets); locally a gitignored .env.testing
 * is read as a fallback so an already-set env var (CI) always wins.
 */
final class PaypalSandbox
{
    private static bool $envLoaded = false;

    public static function loadEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;

        $file = dirname(__DIR__) . '/.env.testing';
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function clientId(): string
    {
        self::loadEnv();
        return (string) getenv('PAYPAL_SANDBOX_CLIENT_ID');
    }

    public static function clientSecret(): string
    {
        self::loadEnv();
        return (string) getenv('PAYPAL_SANDBOX_CLIENT_SECRET');
    }

    public static function isConfigured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }
}
