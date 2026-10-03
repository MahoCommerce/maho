<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Kernel;
use Mcp\Server\Builder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

final class McpInstructionsKernel extends Kernel
{
    public static string $cacheDir = '';

    #[\Override]
    public function getCacheDir(): string
    {
        return self::$cacheDir;
    }

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition('mcp.server.maho.builder')->setPublic(true);
            }
        });
    }
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

test('a percent sign in the store name reaches the MCP server literally', function (): void {
    if (!Mage::helper('apiplatform')->isMcpAvailable()) {
        test()->markTestSkipped('symfony/mcp-bundle and a PSR-17 factory are not installed');
    }

    // %20% is a container parameter reference unless the kernel escapes it.
    $store = Mage::app()->getStore();
    $previousName = $store->getConfig('general/store_information/name');
    $store->setConfig('general/store_information/name', 'Shop%20%');
    McpInstructionsKernel::$cacheDir = sys_get_temp_dir() . '/maho-mcp-instructions-' . bin2hex(random_bytes(6));

    try {
        $kernel = new McpInstructionsKernel('test', true);
        $kernel->boot();
        $builder = $kernel->getContainer()->get('mcp.server.maho.builder');
        expect($builder)->toBeInstanceOf(Builder::class);

        $instructions = new ReflectionProperty(Builder::class, 'instructions')->getValue($builder);
        expect($instructions)->toContain('Store: Shop%20%.');
        $kernel->shutdown();
    } finally {
        $store->setConfig('general/store_information/name', $previousName);
        new Symfony\Component\Filesystem\Filesystem()->remove(McpInstructionsKernel::$cacheDir);
    }
});
