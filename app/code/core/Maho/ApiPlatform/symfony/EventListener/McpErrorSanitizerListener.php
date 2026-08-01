<?php

/**
 * Hide internals from MCP error replies, as ApiExceptionListener does for REST.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Exception\ApiException;
use Mcp\Event\ErrorEvent;
use Mcp\Exception\ExceptionInterface as McpException;
use Mcp\Schema\JsonRpc\Error;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException as SerializerUnexpectedValueException;

/**
 * An exception thrown while a tool runs never reaches `kernel.exception`: the MCP
 * server catches it in its own request loop and answers with
 * `Error::forInternalError($e->getMessage())`, so {@see ApiExceptionListener},
 * which is what keeps REST from returning raw messages, never sees it. Left alone
 * a public read tool would hand an anonymous caller whatever a TypeError or a
 * DBAL failure says. Only the messages REST would also pass through survive.
 */
#[AsEventListener(event: ErrorEvent::class)]
final class McpErrorSanitizerListener
{
    private const CALLER_FACING = [
        ApiException::class,
        AuthenticationException::class,
        AccessDeniedException::class,
        HttpExceptionInterface::class,
        SerializerUnexpectedValueException::class,
        McpException::class,
        \Mage_Core_Exception::class,
    ];

    public function __construct(private readonly bool $debug) {}

    public function __invoke(ErrorEvent $event): void
    {
        $throwable = $event->getThrowable();

        // No throwable: the server built the error itself, nothing of ours in it.
        if ($throwable === null || $this->isCallerFacing($throwable)) {
            return;
        }

        \Mage::logException($throwable);

        if ($this->debug && \Mage::getIsDeveloperMode()) {
            return;
        }

        $error = $event->getError();
        $event->setError(new Error($error->id, $error->code, 'An internal error occurred'));
    }

    private function isCallerFacing(\Throwable $throwable): bool
    {
        return array_any(self::CALLER_FACING, fn(string $type): bool => $throwable instanceof $type);
    }
}
