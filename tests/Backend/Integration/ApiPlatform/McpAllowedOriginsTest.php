<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Kernel;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

uses(Tests\MahoBackendTestCase::class);

/**
 * Send a request with an Origin header to the MCP endpoint of the default store view, through the
 * DNS-rebinding protection that the kernel configures, and return the status code.
 */
function mcpOriginStatus(string $origin): int
{
    $kernel = (new ReflectionClass(Kernel::class))->newInstanceWithoutConstructor();
    $hosts = (new ReflectionMethod(Kernel::class, 'mcpAllowedHosts'))->invoke($kernel);
    $origins = (new ReflectionMethod(Kernel::class, 'mcpAllowedOrigins'))->invoke($kernel);
    $factory = new Psr17Factory();
    $middleware = new DnsRebindingProtectionMiddleware($hosts ?? [], $origins, $factory, $factory);

    $url = Mage::app()->getDefaultStoreView()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, false) . 'api/mcp';
    $request = $factory->createServerRequest('POST', $url)
        ->withHeader('Host', (string) parse_url($url, PHP_URL_HOST))
        ->withHeader('Origin', $origin);

    $handler = new class ($factory) implements RequestHandlerInterface {
        public function __construct(private readonly Psr17Factory $factory) {}

        #[\Override]
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->factory->createResponse(200);
        }
    };

    return $middleware->process($request, $handler)->getStatusCode();
}

it('accepts a request to the MCP endpoint from the origin of another store base URL, and refuses another origin', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    $previous = [
        'web/secure/base_url' => $store->getConfig('web/secure/base_url'),
        'web/secure/base_link_url' => $store->getConfig('web/secure/base_link_url'),
    ];
    try {
        $store->setConfig('web/secure/base_url', 'https://other-store.example:8443/');
        $store->setConfig('web/secure/base_link_url', 'https://other-store.example:8443/');
        (fn() => $this->_baseUrlCache = [])->call($store);

        expect(mcpOriginStatus('https://other-store.example:8443'))->toBe(200);
        expect(mcpOriginStatus('https://attacker.example'))->toBe(403);
    } finally {
        foreach ($previous as $path => $value) {
            $store->setConfig($path, $value);
        }
        (fn() => $this->_baseUrlCache = [])->call($store);
    }
});
