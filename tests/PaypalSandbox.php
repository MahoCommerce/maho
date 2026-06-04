<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests;

use Mage;

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

    /**
     * Set a non-base display currency on the test store (base USD, display EUR), the
     * scenario issue #985 is about. PayPal sandbox credentials are injected globally at
     * install time (see PestTestRunner::injectPaypalSandboxConfig), so they are not set
     * here. The cache is flushed so the served storefront picks up the change.
     */
    public static function configureDisplayCurrency(string $displayCurrency = 'EUR', float $baseToDisplayRate = 0.9): void
    {
        $base = (string) Mage::app()->getStore()->getBaseCurrencyCode() ?: 'USD';

        $config = Mage::getModel('core/config');
        $config->saveConfig('currency/options/allow', implode(',', array_unique([$base, $displayCurrency])));
        $config->saveConfig('currency/options/default', $displayCurrency);
        Mage::getModel('directory/currency')->saveRates([
            $base => [$base => 1.0, $displayCurrency => $baseToDisplayRate],
        ]);

        Mage::app()->getStore()->resetConfig();
        Mage::app()->cleanCache();
    }
}
