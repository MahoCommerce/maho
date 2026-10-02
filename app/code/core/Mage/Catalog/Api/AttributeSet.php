<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Attribute Sets',
    mahoSection: 'Catalog',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    // The processor loads and checks the set itself, so writes skip the provider read
    mahoSelfResolvingWrites: true,
    shortName: 'AttributeSet',
    description: 'Catalog product attribute set metadata',
    provider: AttributeSetProvider::class,
    processor: AttributeSetProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/attribute-sets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
            description: 'Get an attribute set by ID, with its groups and the attributes of each group',
        ),
        new GetCollection(
            uriTemplate: '/attribute-sets',
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/read')",
            description: 'List the attribute sets of products',
        ),
        new Post(
            uriTemplate: '/attribute-sets',
            deserialize: false,
            input: AttributeSetInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/write')",
            description: 'Create a product attribute set. Body: name (required, unique), skeletonId (the ID of an existing set whose groups and attributes the new set copies; default: the default set of products)',
        ),
        new Put(
            uriTemplate: '/attribute-sets/{id}',
            requirements: ['id' => '\d+'],
            deserialize: false,
            input: AttributeSetInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/write')",
            description: 'Rename a product attribute set. Body: name',
        ),
        new Delete(
            uriTemplate: '/attribute-sets/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/delete')",
            description: 'Delete a product attribute set and the products that use it. The default set of products cannot be deleted',
        ),
        new Post(
            uriTemplate: '/attribute-sets/{id}/groups',
            name: 'attribute_set_group_create',
            requirements: ['id' => '\d+'],
            status: 200,
            deserialize: false,
            input: AttributeSetGroupInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/write')",
            description: 'Add an attribute group (a tab of the product form) to an attribute set. Body: name (required, unique in the set), sortOrder. Response: the attribute set',
        ),
        new Post(
            uriTemplate: '/attribute-sets/{id}/attributes',
            name: 'attribute_set_attribute_assign',
            requirements: ['id' => '\d+'],
            status: 200,
            deserialize: false,
            input: AttributeSetAttributeInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/write')",
            description: 'Assign a product attribute to a group of an attribute set, or move it to another group. Body: attributeId or attributeCode (required), groupId or groupName (required), sortOrder. Response: the attribute set',
        ),
        new Delete(
            uriTemplate: '/attribute-sets/{id}/attributes/{attributeId}',
            name: 'attribute_set_attribute_unassign',
            requirements: ['id' => '\d+', 'attributeId' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('attribute-sets/write')",
            description: 'Remove a user defined product attribute from an attribute set. Products of the set lose their values for it. A system attribute, or an attribute that configurable products of the set use, cannot be removed',
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
    ],
)]
class AttributeSet extends CrudResource
{
    public const MODEL = 'eav/entity_attribute_set';
    public const PRIMARY_KEY = 'attribute_set_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Catalog_Product_SetController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_Product_SetController::ADMIN_RESOURCE;

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

    /**
     * Attribute groups with their assigned attributes. Populated by the provider.
     *
     * @var array<array{id: int, name: string, sortOrder: int, attributes: array<array{id: int, code: string, sortOrder: int}>}>
     */
    #[ApiProperty(writable: false, description: 'Attribute groups: [{id, name, sortOrder, attributes: [{id, code, sortOrder}]}]', extraProperties: ['computed' => true])]
    public array $groups = [];
}
