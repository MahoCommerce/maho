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
    public static function loadEnv(): void
    {
        TestEnv::load();
    }

    public static function clientId(): string
    {
        return TestEnv::get('PAYPAL_SANDBOX_CLIENT_ID');
    }

    public static function clientSecret(): string
    {
        return TestEnv::get('PAYPAL_SANDBOX_CLIENT_SECRET');
    }

    public static function isConfigured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }
}
