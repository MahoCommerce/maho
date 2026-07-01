<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\GraphQl\CustomQueryResolver;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Product Attributes',
    mahoSection: 'Catalog',
    mahoOperations: ['read' => 'View'],
    shortName: 'ProductAttribute',
    description: 'Catalog product attribute metadata',
    provider: ProductAttributeProvider::class,
    operations: [
        new Get(
            uriTemplate: '/product-attributes/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
            description: 'Get a product attribute by ID',
        ),
        new GetCollection(
            uriTemplate: '/product-attributes',
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
            description: 'Get product attribute collection',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a product attribute by ID (canonical)',
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get product attributes (canonical)',
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
        ),
        new QueryCollection(
            name: 'productAttributes',
            description: 'Get product attributes',
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
        ),
        new Query(
            name: 'productAttribute',
            description: 'Get a product attribute by code',
            args: ['code' => ['type' => 'String!']],
            resolver: CustomQueryResolver::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
        ),
    ],
)]
class ProductAttribute extends CrudResource
{
    public const MODEL = 'catalog/resource_eav_attribute';
    public const PRIMARY_KEY = 'attribute_id';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false, description: 'Attribute code')]
    public ?string $attributeCode = null;

    #[ApiProperty(writable: false, description: 'Admin frontend label')]
    public ?string $frontendLabel = null;

    #[ApiProperty(writable: false, description: 'Frontend input type (text, select, multiselect, etc.)')]
    public ?string $frontendInput = null;

    #[ApiProperty(writable: false, description: 'Backend storage type (varchar, int, text, decimal, datetime, static)')]
    public ?string $backendType = null;

    #[ApiProperty(writable: false, description: 'Whether a value is required')]
    public bool $isRequired = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute was created by a user (not a system attribute)')]
    public bool $isUserDefined = false;

    #[ApiProperty(writable: false, description: 'Whether values must be unique across products')]
    public bool $isUnique = false;

    #[ApiProperty(writable: false, description: 'Value scope: global, website, or store', extraProperties: ['computed' => true])]
    public string $scope = 'global';

    #[ApiProperty(writable: false, description: 'Default value')]
    public ?string $defaultValue = null;

    /**
     * Source options for select/multiselect attributes. Populated by the provider.
     *
     * @var array<array{label: string, value: string}>
     */
    #[ApiProperty(writable: false, description: 'Source options for select/multiselect attributes', extraProperties: ['computed' => true])]
    public array $options = [];

    /**
     * Derive the human-readable scope from the catalog is_global flag.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        $dto->scope = match ((int) $model->getData('is_global')) {
            \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE => 'website',
            \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE => 'store',
            default => 'global',
        };
    }
}
