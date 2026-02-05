<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Maho\ApiPlatform\State\Provider\CountryProvider;

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
)]
class Country
{
    public ?string $id = null;  // ISO country code (AU, US, etc.)
    public string $name = '';
    public ?string $iso2Code = null;
    public ?string $iso3Code = null;
    /** @var Region[] */
    public array $available_regions = [];
}
