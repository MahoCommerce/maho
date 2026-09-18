<?php

/**
 * Hands the HTTP QUERY body to collection providers as their filters.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * API Platform's ParameterProvider parses a QUERY body (RFC 10008) into the
 * `_api_query_parameters` request attribute, but ReadProvider builds
 * `$context['filters']` from `_api_filters` or the URI query string only. Sitting
 * between the two, this copies the body over so a collection provider sees the
 * same filters a GET carries in its query string.
 *
 * @implements ProviderInterface<object>
 */
final class QueryBodyFiltersProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(private readonly ProviderInterface $decorated) {}

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $context['request'] ?? null;
        if (
            $request instanceof Request
            && $operation instanceof HttpOperation
            && strtoupper((string) $operation->getMethod()) === HttpOperation::METHOD_QUERY
            && !$request->attributes->has('_api_filters')
        ) {
            $body = $request->attributes->get('_api_query_parameters');
            if (is_array($body) && $body !== []) {
                $request->attributes->set('_api_filters', $body);
            }
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
