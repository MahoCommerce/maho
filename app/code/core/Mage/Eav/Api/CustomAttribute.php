<?php

/**
 * An attribute that an admin added to an entity type of System > Attributes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

namespace Mage\Eav\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\Metadata\EnumSource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Custom Attributes',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View'],
    shortName: 'CustomAttribute',
    description: 'The attributes that an admin added to categories, customers, customer addresses, CMS pages, blog posts and blog categories. The customAttributes field of these resources takes their codes',
    provider: CustomAttributeProvider::class,
    operations: [
        new Get(
            uriTemplate: '/custom-attributes/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('custom-attributes/read')",
            description: 'Get a custom attribute by ID, with its options and the attribute sets that hold it',
        ),
        new GetCollection(
            uriTemplate: '/custom-attributes',
            paginationEnabled: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('custom-attributes/read')",
            description: 'List the custom attributes of an entity type, with their options and the attribute sets that hold them. '
                . 'Query: entityType (required): catalog_category, customer, customer_address, cms_page, blog_post or blog_category. '
                . 'Product attributes are at /product-attributes',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a custom attribute by ID',
            security: "is_granted('ROLE_ADMIN') or is_granted('custom-attributes/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'Get the custom attributes of an entity type',
            paginationEnabled: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('custom-attributes/read')",
            extraArgs: [
                'entityType' => ['type' => 'String!', 'description' => 'catalog_category, customer, customer_address, cms_page, blog_post or blog_category'],
            ],
        ),
    ],
)]
class CustomAttribute extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'system/attributes';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'Attribute code: the key of the attribute in customAttributes')]
    public string $code = '';

    #[ApiProperty(description: 'Entity type of the attribute', extraProperties: [EnumSource::KEY => 'Maho\ApiPlatform\Metadata\ValueLists::customAttributeEntityTypes'])]
    public string $entityType = '';

    #[ApiProperty(description: 'Admin label')]
    public string $label = '';

    #[ApiProperty(description: 'Input type, for example text, textarea, date, boolean, select, multiselect or multiline')]
    public string $input = '';

    #[ApiProperty(description: 'Whether a value is required')]
    public bool $isRequired = false;

    #[ApiProperty(description: 'Whether two records cannot have the same value')]
    public bool $isUnique = false;

    #[ApiProperty(description: 'Default value of a new record, in the shape of customAttributes')]
    public mixed $defaultValue = null;

    /** @var list<array{value: int|string, label: string}> */
    #[ApiProperty(description: 'Options of a dropdown or a multiselect, with their admin labels. customAttributes takes the value; a multiselect takes a list of values. A Yes/No attribute takes a boolean')]
    public array $options = [];

    /** @var list<array{id: int, name: string, groupId: int, groupName: string}> */
    #[ApiProperty(description: 'The attribute sets that hold the attribute, with the group in each set. A record accepts the attribute only when its set holds it. For customers and addresses, the customer group picks the set')]
    public array $attributeSets = [];

    /** @var array<int|string, mixed>|null */
    #[ApiProperty(description: 'The condition that shows the field in a form, or null when the form always shows it. '
        . 'It maps a field code to its wanted values, for example {"country_id": ["IT"]}. An entry with an operator holds a group: '
        . '{"operator": "OR", "condition": {...}}. The operator is AND (each entry), OR (any entry), NOT (no entry) or XOR (exactly one entry), '
        . 'and the entries of one level are joined with AND. A field that the condition hides is not required, and a save does not keep its value')]
    public ?array $dependsOn = null;

    /** @var list<string>|null */
    #[ApiProperty(description: 'Customer and address attributes only: the forms that show the attribute. The API checks a customer against adminhtml_customer and an address against adminhtml_customer_address')]
    public ?array $usedInForms = null;
}
