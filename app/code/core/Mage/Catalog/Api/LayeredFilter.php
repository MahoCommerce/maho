<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Query;

#[ApiResource(
    shortName: 'LayeredFilter',
    description: 'Layered navigation filters (facets) for a category',
    provider: LayeredFilterProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/layered-filters',
            security: 'true',
            description: 'Get available filters for a category',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a layered filter', security: 'true'),
        new QueryCollection(name: 'collection_query', description: 'Get layered filters', security: 'true'),
        new QueryCollection(
            name: 'layeredFilters',
            args: [
                'categoryId' => ['type' => 'Int!', 'description' => 'Category ID to get filters for'],
            ],
            description: 'Get available layered navigation filters for a category',
        ),
    ],
)]
class LayeredFilter extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true, description: 'Attribute code')]
    public string $code = '';

    #[ApiProperty(description: 'Display label')]
    public string $label = '';

    #[ApiProperty(description: 'Frontend input type: select, multiselect, price')]
    public string $type = 'select';

    #[ApiProperty(description: 'Sort position')]
    public int $position = 0;

    /** @var array<int, array<string, mixed>> FilterOption entries; plain-DTO elements kept as Iterable scalar. */
    #[ApiProperty(description: 'Available filter options with counts')]
    public array $options = [];
}
