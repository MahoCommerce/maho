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
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Attribute Sets',
    mahoSection: 'Catalog',
    mahoOperations: ['read' => 'View'],
    shortName: 'AttributeSet',
    description: 'Catalog product attribute set metadata',
    provider: AttributeSetProvider::class,
    operations: [
        new Get(
            uriTemplate: '/attribute-sets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
            description: 'Get an attribute set by ID',
        ),
        new GetCollection(
            uriTemplate: '/attribute-sets',
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
            description: 'Get attribute set collection',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get an attribute set by ID (canonical)',
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get attribute sets (canonical)',
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
        ),
        new Query(
            name: 'attributeSet',
            description: 'Get an attribute set by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
        ),
        new QueryCollection(
            name: 'attributeSets',
            description: 'Get attribute sets',
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
        ),
    ],
)]
class AttributeSet extends CrudResource
{
    public const MODEL = 'eav/entity_attribute_set';
    public const PRIMARY_KEY = 'attribute_set_id';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false, description: 'Attribute set name')]
    public ?string $attributeSetName = null;

    /**
     * Attribute codes assigned to this set. Populated by the provider.
     *
     * @var string[]
     */
    #[ApiProperty(writable: false, description: 'Attribute codes assigned to this set', extraProperties: ['computed' => true])]
    public array $attributeCodes = [];
}
