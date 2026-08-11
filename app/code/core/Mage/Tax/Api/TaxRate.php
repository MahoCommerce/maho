<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoLabel: 'Tax Rates',
    mahoSection: 'Tax',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'TaxRate',
    description: 'Tax Rate resource',
    provider: TaxRateProvider::class,
    processor: TaxRateProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/tax-rates/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/read')",
        ),
        new GetCollection(
            uriTemplate: '/tax-rates',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/read')",
        ),
        new Post(
            uriTemplate: '/tax-rates',
            processor: TaxRateProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/write')",
            description: 'Creates a new tax rate',
        ),
        new Put(
            uriTemplate: '/tax-rates/{id}',
            processor: TaxRateProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/write')",
            description: 'Updates a tax rate',
        ),
        new Delete(
            uriTemplate: '/tax-rates/{id}',
            processor: TaxRateProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/delete')",
            description: 'Deletes a tax rate',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a tax rate by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get tax rates',
            security: "is_granted('ROLE_ADMIN') or is_granted('tax-rates/read')",
            extraArgs: [
                'search' => ['type' => 'String', 'description' => 'Partial match on the rate code'],
                'taxCountryId' => ['type' => 'String', 'description' => 'Exact two-letter country code'],
            ],
        ),
    ],
)]
class TaxRate extends CrudResource
{
    public const MODEL = 'tax/calculation_rate';

    public const PRIMARY_KEY = 'tax_calculation_rate_id';

    /**
     * Admin ACL gate. Mirrors the backend tax rate page (sales/tax/rates).
     * Mage_Adminhtml_Tax_RateController declares no ADMIN_RESOURCE of its own,
     * so the ACL node from app/code/core/Mage/Tax/etc/adminhtml.xml is used.
     */
    public const ADMIN_RESOURCE = 'sales/tax/rates';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    /**
     * code, taxCountryId and rate are nullable so that a partial PUT that omits
     * them leaves the stored value alone: applyToModel() skips null but writes a
     * non-null default, which would blank a required column.
     */
    public ?string $code = null;

    #[ApiProperty(extraProperties: ['modelField' => 'tax_country_id'])]
    public ?string $taxCountryId = null;

    #[ApiProperty(extraProperties: ['modelField' => 'tax_region_id'])]
    public ?int $taxRegionId = null;

    #[ApiProperty(extraProperties: ['modelField' => 'tax_postcode'])]
    public ?string $taxPostcode = null;

    public ?float $rate = null;

    #[ApiProperty(extraProperties: ['modelField' => 'zip_is_range'])]
    public ?bool $zipIsRange = null;

    #[ApiProperty(extraProperties: ['modelField' => 'zip_from'])]
    public ?string $zipFrom = null;

    #[ApiProperty(extraProperties: ['modelField' => 'zip_to'])]
    public ?string $zipTo = null;

    /**
     * Per-store display titles (tax_calculation_rate_title). Null on input means
     * untouched; an entry with an empty title clears that store's title.
     *
     * @var array<int, array{storeId: int, title: string}>|null
     */
    #[ApiProperty(extraProperties: ['computed' => true])]
    public ?array $titles = null;

    public static function afterLoad(self $dto, object $model): void
    {
        if (!$model->getId()) {
            return;
        }

        /** @var \Mage_Tax_Model_Calculation_Rate $model */
        $titles = [];
        foreach ($model->getTitles() as $title) {
            $titles[] = [
                'storeId' => (int) $title->getStoreId(),
                'title' => (string) $title->getValue(),
            ];
        }
        $dto->titles = $titles;
    }
}
