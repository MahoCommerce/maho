<?php

/**
 * A query string filter of a REST list that the provider reads itself.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use ApiPlatform\Metadata\QueryParameter;

/**
 * Declare it in the `parameters` of a GetCollection. The OpenAPI document shows it, and the MCP
 * list tool offers it when the resource has no GraphQL list query to read the filters from:
 *
 *     parameters: ['state' => new ListFilter('Only the invoices in this state', enum: ['open', 'paid', 'canceled'])]
 *
 * It only describes the filter. The provider still reads and checks the value, so the API
 * gives the same answer as before: no schema validation, and no hydra:search mapping.
 */
final class ListFilter extends QueryParameter
{
    /** @param list<string>|null $enum */
    public function __construct(string $description, string $type = 'string', ?array $enum = null)
    {
        parent::__construct(
            schema: array_filter(['type' => $type, 'enum' => $enum], static fn(mixed $value): bool => $value !== null),
            description: $description,
            hydra: false,
            constraints: [],
        );
    }
}
