<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Kernel;

uses(Tests\MahoBackendTestCase::class);

/**
 * The MCP instruction line is baked into the compiled container and a
 * service-account token reads base currency, so it must name base, not the
 * default store view's display currency.
 */

function mcpInstructionsText(): string
{
    $method = new ReflectionMethod(Kernel::class, 'mcpInstructions');
    $kernel = (new ReflectionClass(Kernel::class))->newInstanceWithoutConstructor();

    return (string) $method->invoke($kernel);
}

afterEach(function (): void {
    resetCurrencyState();
});

test('the instructions name the base currency, not the default view display currency', function (): void {
    $store = Mage::app()->getDefaultStoreView();

    if ($store === null || $store->getBaseCurrencyCode() !== 'USD') {
        test()->markTestSkipped('Test expects a default store view on a USD base currency website');
    }

    setStoreDisplayCurrency('EUR', 'USD,EUR', (int) $store->getId());

    expect($store->getDefaultCurrencyCode())->toBe('EUR');

    $instructions = mcpInstructionsText();

    expect($instructions)->toContain('amounts are in USD, the base currency of the default website');
    expect($instructions)->toContain('"currency" field');

    // Base currency is website-scoped and the line is compiled once, so it
    // must not read as global, nor as covering cart and order amounts.
    expect($instructions)->toContain('other websites may differ');
    expect($instructions)->toContain('not always USD');

    // Only the currency line: the store name may legitimately contain "EUR".
    $currencyLine = array_find(
        explode("\n", $instructions),
        fn(string $line): bool => str_contains($line, 'base currency of the default website'),
    );
    expect($currencyLine)->not->toBeNull();
    expect($currencyLine)->not->toContain('EUR');
});
