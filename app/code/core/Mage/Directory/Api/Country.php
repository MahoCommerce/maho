<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

namespace Mage\Directory\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoSection: 'System',
    mahoOperations: ['read' => 'View'],
    shortName: 'Country',
    description: 'Country and region resource for addresses',
    provider: CountryProvider::class,
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
        new Query(name: 'item_query', description: 'Get a country', security: 'true'),
        new QueryCollection(name: 'collection_query', description: 'Get countries', security: 'true'),
        new QueryCollection(name: 'countries', description: 'Get all available countries with regions'),
        new Query(
            name: 'country',
            args: ['id' => ['type' => 'String!', 'description' => 'ISO 2-letter country code']],
            description: 'Get a country by ISO code',
        ),
    ],
)]
class Country extends CrudResource
{
    public const MODEL = 'directory/country';

    #[ApiProperty(identifier: true, writable: false, extraProperties: ['modelField' => 'country_id'])]
    public ?string $id = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $name = '';

    #[ApiProperty(writable: false)]
    public ?string $iso2Code = null;

    #[ApiProperty(writable: false)]
    public ?string $iso3Code = null;

    /** @var array<int, array<string, mixed>> Regions/states for the country; Region is a plain DTO (not a standalone ApiResource per its own docblock) so kept as Iterable scalar. */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public array $availableRegions = [];

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->name = $model->getName() ?? $model->getCountryId() ?? '';

        $regions = [];
        /** @var \Mage_Directory_Model_Resource_Region_Collection $collection */
        $collection = \Mage::getModel('directory/region')->getCollection();
        $collection->addCountryFilter($model->getCountryId());

        foreach ($collection as $mahoRegion) {
            // Plain array shape (not a Region DTO) to match the
            // array<int, array<string, mixed>> property type. Region is a
            // non-ApiResource DTO so the typed-array form trips GraphQL's
            // CursorConnection wrapping with null edges.
            $regions[] = [
                'id'   => (int) $mahoRegion->getRegionId(),
                'code' => $mahoRegion->getCode() ?? '',
                'name' => $mahoRegion->getDefaultName() ?? $mahoRegion->getName() ?? '',
            ];
        }

        usort($regions, fn($a, $b) => strcmp($a['name'], $b['name']));
        $dto->availableRegions = $regions;
    }
}
