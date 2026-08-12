<?php

/**
 * Expresses an MCP tool call as the REST request it mirrors.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Mcp;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpFoundation\Request;

/**
 * Providers and processors read plain path parameters off `$request->getPathInfo()`,
 * because API Platform only populates `uriVariables` declared as `Link` objects. Under
 * MCP every request is a POST to `/api/mcp`, so those reads come back empty: a product
 * link loses which of related/cross-sell/up-sell it meant, a guest cart loses its
 * masked id.
 *
 * Some read the body straight off the request too, where MCP would hand them the
 * JSON-RPC envelope instead of the tool arguments.
 *
 * This hands them a duplicate of the real request carrying the operation's URI, with
 * variables substituted, and the tool arguments as its body. Server params are kept, so
 * the host, the client address and every header survive: rate limiting and
 * header-authenticated operations keep working.
 *
 * Used by both {@see \Maho\ApiPlatform\State\McpDispatchProvider} and
 * {@see \Maho\ApiPlatform\State\McpWriteProcessor}: the MCP handler builds one context
 * and passes it to the read and write stages separately, so neither can hand the other
 * a modified copy.
 */
final class OperationRequestFactory
{
    /** Prefix the REST routes carry, so a substituted path matches what a provider expects. */
    private const ROUTE_PREFIX = '/api/rest/v2';

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $arguments
     */
    public function create(HttpOperation $operation, array $uriVariables, array $arguments, Request $original): Request
    {
        $template = str_replace('{._format}', '', (string) $operation->getUriTemplate());
        $values = $uriVariables + $arguments;

        // A placeholder with no value is left as-is: the provider's own pattern won't
        // match it, which is the same outcome as today rather than a worse one.
        $path = preg_replace_callback(
            '#\{(\w+)\}#',
            static function (array $matches) use ($values): string {
                $value = $values[$matches[1]] ?? null;

                return is_scalar($value) ? rawurlencode((string) $value) : $matches[0];
            },
            $template,
        ) ?? $template;

        $duplicate = $original->duplicate(server: ['REQUEST_URI' => self::ROUTE_PREFIX . $path] + $original->server->all());

        // initialize() is the only way to give a Request a different body. Every bag is
        // passed back unchanged so only the content differs; it also clears the cached
        // path values, which is what makes the substituted URI take effect.
        $duplicate->initialize(
            $duplicate->query->all(),
            $duplicate->request->all(),
            $duplicate->attributes->all(),
            $duplicate->cookies->all(),
            $duplicate->files->all(),
            $duplicate->server->all(),
            json_encode($arguments, JSON_THROW_ON_ERROR),
        );

        return $duplicate;
    }

    /**
     * Placeholder names in the operation's URI, as a set. Read from the template rather
     * than from `getUriVariables()` because a plain (non-`Link`) path parameter never
     * appears there.
     *
     * @return array<string, true>
     */
    public function uriVariableNames(HttpOperation $operation): array
    {
        preg_match_all('#\{(\w+)\}#', (string) $operation->getUriTemplate(), $matches);

        return array_fill_keys($matches[1], true);
    }
}
