<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Catalog\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\Catalog\Api\State\Provider\LayeredFilterProvider;

#[ApiResource(
    shortName: 'LayeredFilter',
    description: 'Layered navigation filters (facets) for a category',
    provider: LayeredFilterProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/layered-filters',
            description: 'Get available filters for a category',
        ),
    ],
    graphQlOperations: [
        new QueryCollection(
            name: 'layeredFilters',
            args: [
                'categoryId' => ['type' => 'Int!', 'description' => 'Category ID to get filters for'],
            ],
            description: 'Get available layered navigation filters for a category',
        ),
    ],
)]
class LayeredFilter
{
    #[ApiProperty(identifier: true, description: 'Attribute code')]
    public string $code = '';

    #[ApiProperty(description: 'Display label')]
    public string $label = '';

    #[ApiProperty(description: 'Frontend input type: select, multiselect, price')]
    public string $type = 'select';

    #[ApiProperty(description: 'Sort position')]
    public int $position = 0;

    /** @var FilterOption[] */
    #[ApiProperty(description: 'Available filter options with counts')]
    public array $options = [];
}
