<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests;

/**
 * Credentials for tests that talk to real third-party services.
 *
 * Source of truth is the environment (CI secrets); locally a gitignored .env.testing
 * is read as a fallback so an already-set env var (CI) always wins.
 */
final class TestEnv
{
    private static bool $loaded = false;

    public static function get(string $name): string
    {
        self::load();
        return (string) getenv($name);
    }

    public static function has(string ...$names): bool
    {
        foreach ($names as $name) {
            if (self::get($name) === '') {
                return false;
            }
        }
        return true;
    }

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

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
            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
