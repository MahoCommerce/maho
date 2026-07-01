<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

namespace Mage\Directory\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    security: 'true',
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
class Currency extends CrudResource
{
    public const MODEL = 'directory/currency';

    #[ApiProperty(identifier: true, writable: false, extraProperties: ['modelField' => 'currency_code'])]
    public string $code = '';

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $symbol = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?float $exchangeRate = null;
}
