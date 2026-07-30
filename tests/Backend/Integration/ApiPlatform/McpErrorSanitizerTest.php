<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\EventListener\McpErrorSanitizerListener;
use Mcp\Event\ErrorEvent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * The MCP server answers with Error::forInternalError($e->getMessage()) from its
 * own request loop, so ApiExceptionListener never sees the exception and a public
 * tool would hand an anonymous caller whatever a DBAL failure says.
 */

function mcpErrorMessageFor(\Throwable $throwable, bool $debug = false): string
{
    $event = new ErrorEvent(
        Error::forInternalError($throwable->getMessage(), 1),
        new CallToolRequest('catalog_products_list', []),
        new Session(new InMemorySessionStore()),
        $throwable,
    );

    (new McpErrorSanitizerListener($debug))($event);

    return $event->getError()->message;
}

it('replaces a message no REST response would have carried', function (): void {
    $messages = [
        mcpErrorMessageFor(new RuntimeException('SQLSTATE[42S02]: table catalog_product_entity not found')),
        mcpErrorMessageFor(new TypeError('Argument #1 must be int, string given, called in /app/code/core/X.php')),
    ];

    expect($messages)->each->toBe('An internal error occurred');
});

it('keeps the messages written for the caller', function (): void {
    expect(mcpErrorMessageFor(new AccessDeniedHttpException('Role x does not grant access to sales/order')))
        ->toBe('Role x does not grant access to sales/order');
    expect(mcpErrorMessageFor(new InsufficientAuthenticationException('Authentication required: send a token.')))
        ->toBe('Authentication required: send a token.');
    expect(mcpErrorMessageFor(new NotFoundHttpException('Not Found')))->toBe('Not Found');
    expect(mcpErrorMessageFor(new Mage_Core_Exception('This product is out of stock.')))
        ->toBe('This product is out of stock.');
    expect(mcpErrorMessageFor(new Mcp\Exception\InvalidArgumentException('Invalid "name" in params.')))
        ->toBe('Invalid "name" in params.');
});

it('leaves an error the server raised on its own alone', function (): void {
    $event = new ErrorEvent(
        Error::forMethodNotFound('No handler found for method "foo/bar".', 1),
        new CallToolRequest('catalog_products_list', []),
        new Session(new InMemorySessionStore()),
        null,
    );

    (new McpErrorSanitizerListener(false))($event);

    expect($event->getError()->message)->toBe('No handler found for method "foo/bar".');
});
