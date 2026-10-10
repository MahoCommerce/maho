<?php

/**
 * Runs one MCP tool call in-process through the API Platform MCP handler.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use ApiPlatform\Mcp\Server\Handler;
use Maho\ApiPlatform\EventListener\McpErrorSanitizerListener;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The handler is the path `/api/mcp` takes after transport decoding, so a call made here
 * meets the same provider chain, including the admin ACL gate in
 * {@see \Maho\ApiPlatform\State\McpDispatchProvider}. Failures come back as data for the
 * model, never as an exception that would end the chat turn.
 */
final class McpToolDispatcher
{
    public function __construct(
        #[Autowire(service: 'api_platform.mcp.handler')]
        private readonly Handler $handler,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function call(string $toolName, array $arguments): array
    {
        $request = CallToolRequest::fromArray([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => CallToolRequest::getMethod(),
            'params' => ['name' => $toolName, 'arguments' => $arguments === [] ? new \stdClass() : $arguments],
        ]);

        try {
            $reply = $this->handler->handle($request, new Session(new InMemorySessionStore()));
        } catch (\Throwable $e) {
            if (McpErrorSanitizerListener::isCallerFacing($e)) {
                return ['ok' => false, 'text' => sprintf('Tool refused: %s', $e->getMessage())];
            }
            \Mage::logException($e);

            return [
                'ok' => false,
                'text' => \Mage::getIsDeveloperMode()
                    ? sprintf('Tool failed: %s', $e->getMessage())
                    : 'Tool failed because of an internal error. It was logged for the administrator.',
            ];
        }

        if ($reply instanceof Error) {
            return ['ok' => false, 'text' => sprintf('Tool refused: %s', $reply->message)];
        }

        return $this->unwrap($reply);
    }

    /**
     * @param Response<mixed> $reply
     * @return array{ok: bool, text: string}
     */
    private function unwrap(Response $reply): array
    {
        $result = $reply->result;
        if (!$result instanceof CallToolResult) {
            return ['ok' => true, 'text' => $this->json($result)];
        }

        $texts = [];
        foreach ($result->content as $content) {
            if ($content instanceof TextContent) {
                $texts[] = is_string($content->text) ? $this->plainJson($content->text) : $this->json($content->text);
            }
        }
        $text = implode("\n", $texts);
        if ($text === '' && $result->structuredContent !== null) {
            $text = $this->json($result->structuredContent);
        }

        return ['ok' => !$result->isError, 'text' => $text];
    }

    /**
     * API Platform encodes JSON with hex escapes, so "<" arrives as "\u003C". A model copies
     * such text back verbatim, so the result is re-encoded as plain characters.
     */
    private function plainJson(string $text): string
    {
        if ($text === '' || !in_array($text[0], ['{', '['], true)) {
            return $text;
        }
        try {
            return $this->json(json_decode($text, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return $text;
        }
    }

    private function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return '';
        }
    }
}
