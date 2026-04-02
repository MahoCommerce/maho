<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Directory\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    shortName: 'Currency',
    description: 'Available currencies with exchange rates',
    provider: CurrencyProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/stores/currencies',
            name: 'list_currencies',
            security: 'true',
            description: 'Get available currencies with exchange rates',
        ),
    ],
)]
class Currency extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public string $code = '';

    public ?string $symbol = null;
    public ?float $exchangeRate = null;
}
