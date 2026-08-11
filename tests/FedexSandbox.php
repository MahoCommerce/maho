<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests;

/**
 * Loads FedEx sandbox credentials for E2E tests.
 *
 * Source of truth is the environment (CI secrets); locally a gitignored .env.testing
 * is read as a fallback so an already-set env var (CI) always wins.
 */
final class FedexSandbox
{
    public static function clientId(): string
    {
        return TestEnv::get('FEDEX_SANDBOX_CLIENT_ID');
    }

    public static function clientSecret(): string
    {
        return TestEnv::get('FEDEX_SANDBOX_CLIENT_SECRET');
    }

    public static function account(): string
    {
        return TestEnv::get('FEDEX_SANDBOX_ACCOUNT');
    }

    /**
     * Which rate product the sandbox project is entitled to.
     *
     * The Maho default is the standard Rate API, but a FedEx project registered as an
     * Integrator Provider is only allowed the comprehensive one, so the environment
     * picks rather than the test hardcoding it.
     */
    public static function rateEndpoint(): string
    {
        return TestEnv::get('FEDEX_SANDBOX_RATE_ENDPOINT')
            ?: \Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_ENDPOINT_STANDARD;
    }

    public static function isConfigured(): bool
    {
        return TestEnv::has('FEDEX_SANDBOX_CLIENT_ID', 'FEDEX_SANDBOX_CLIENT_SECRET');
    }
}
