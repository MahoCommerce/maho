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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
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

    /** @var Region[] */
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
            $region = new Region();
            $region->id = (int) $mahoRegion->getRegionId();
            $region->code = $mahoRegion->getCode() ?? '';
            $region->name = $mahoRegion->getDefaultName() ?? $mahoRegion->getName() ?? '';
            $regions[] = $region;
        }

        usort($regions, fn($a, $b) => strcmp($a->name, $b->name));
        $dto->availableRegions = $regions;
    }
}
