<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Directory\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\Directory\Api\State\Provider\CountryProvider;

#[ApiResource(
    shortName: 'Country',
    description: 'Country and region resource for addresses',
    provider: CountryProvider::class,
    operations: [
        new Get(
            uriTemplate: '/countries/{id}',
            description: 'Get a country by ISO code',
        ),
        new GetCollection(
            uriTemplate: '/countries',
            description: 'Get all available countries',
        ),
    ],
    graphQlOperations: [
        new QueryCollection(name: 'countries', description: 'Get all available countries with regions'),
        new Query(
            name: 'country',
            args: ['id' => ['type' => 'String!', 'description' => 'ISO 2-letter country code']],
            description: 'Get a country by ISO code',
        ),
    ],
)]
class Country
{
    public ?string $id = null;  // ISO country code (AU, US, etc.)
    public string $name = '';
    public ?string $iso2Code = null;
    public ?string $iso3Code = null;
    /** @var Region[] */
    public array $availableRegions = [];
}
