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

    expect($instructions)->toContain('Amounts are in USD unless a response says otherwise');
    expect($instructions)->toContain('"currency" field');

    // Scope the EUR check to the currency line: the store name is in the same
    // text and may legitimately contain "EUR".
    $currencyLine = array_find(
        explode("\n", $instructions),
        fn(string $line): bool => str_contains($line, 'Amounts are in'),
    );
    expect($currencyLine)->not->toContain('EUR');
});
