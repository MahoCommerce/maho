<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Directory\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;

#[ApiResource(
    shortName: 'Country',
    description: 'Country and region resource for addresses',
    provider: CountryReader::class,
    operations: [
        new Get(
            uriTemplate: '/countries/{id}',
            security: 'true',
            description: 'Get a country by ISO code',
        ),
        new GetCollection(
            uriTemplate: '/countries',
            security: 'true',
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
