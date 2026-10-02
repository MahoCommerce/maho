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
    mahoLabel: 'Product Attributes',
    mahoSection: 'Catalog',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    // The processor loads and checks the attribute itself, so writes skip the provider read
    mahoSelfResolvingWrites: true,
    shortName: 'ProductAttribute',
    description: 'Catalog product attribute metadata',
    provider: ProductAttributeProvider::class,
    processor: ProductAttributeProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/product-attributes/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
            description: 'Get a product attribute by ID, with its options when it is a select or multiselect attribute',
        ),
        new GetCollection(
            uriTemplate: '/product-attributes',
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/read')",
            description: 'List product attributes. Filter: search (partial match on the attribute code or label)',
        ),
        new Post(
            uriTemplate: '/product-attributes',
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/write')",
            description: 'Create a product attribute. Required: attributeCode (letters a-z, digits and underscores, starts with a letter, at most 30 characters) and frontendLabel. '
                . 'frontendInput is text, textarea, date, boolean, select, multiselect or price (default text) and sets the storage type. '
                . 'scope is global, website or store (default global). The attribute is user defined and belongs to no attribute set until POST /attribute-sets/{id}/attributes assigns it. '
                . 'Options of a select or multiselect attribute: POST /product-attributes/{id}/options',
        ),
        new Put(
            uriTemplate: '/product-attributes/{id}',
            requirements: ['id' => '\d+'],
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/write')",
            description: 'Update a product attribute. Only the fields in the body change. attributeCode and frontendInput cannot change after creation. '
                . 'A system attribute accepts only its storefront and admin flags',
        ),
        new Delete(
            uriTemplate: '/product-attributes/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/delete')",
            description: 'Delete a user defined product attribute and the values that products hold for it. A system attribute cannot be deleted',
        ),
        new Post(
            uriTemplate: '/product-attributes/{id}/options',
            name: 'product_attribute_option_create',
            requirements: ['id' => '\d+'],
            status: 200,
            deserialize: false,
            input: ProductAttributeOptionInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/write')",
            description: 'Add an option to a select or multiselect attribute. Body: label (required, the admin label), sortOrder, isDefault (true makes it the default value), '
                . 'storeLabels (an object of store view code to label). Response: the attribute with its options; the value of each option is the option ID',
        ),
        new Put(
            uriTemplate: '/product-attributes/{id}/options/{optionId}',
            name: 'product_attribute_option_update',
            requirements: ['id' => '\d+', 'optionId' => '\d+'],
            deserialize: false,
            input: ProductAttributeOptionInput::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/write')",
            description: 'Update an option of a select or multiselect attribute. Only the fields in the body change: label, sortOrder, isDefault, storeLabels (a store view code with an empty label removes that store label). Response: the attribute with its options',
        ),
        new Delete(
            uriTemplate: '/product-attributes/{id}/options/{optionId}',
            name: 'product_attribute_option_delete',
            requirements: ['id' => '\d+', 'optionId' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('product-attributes/write')",
            description: 'Delete an option of a select or multiselect attribute. Products that hold this value lose it',
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
            extraArgs: [
                'code' => ['type' => 'String', 'description' => 'Exact attribute-code lookup (returns 0 or 1 attribute)'],
                'search' => ['type' => 'String', 'description' => 'Partial match on the attribute code or label'],
            ],
        ),
    ],
)]
class ProductAttribute extends CrudResource
{
    public const MODEL = 'catalog/resource_eav_attribute';
    public const PRIMARY_KEY = 'attribute_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_Catalog_Product_AttributeController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_Product_AttributeController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'Attribute code. Set on create only: letters a-z, digits and underscores, starts with a letter, at most 30 characters')]
    public ?string $attributeCode = null;

    #[ApiProperty(description: 'Admin label, required on create')]
    public ?string $frontendLabel = null;

    #[ApiProperty(description: 'Input type, set on create only: text, textarea, date, boolean, select, multiselect or price', openapiContext: ['enum' => ['text', 'textarea', 'date', 'boolean', 'select', 'multiselect', 'price']])]
    public ?string $frontendInput = null;

    #[ApiProperty(writable: false, description: 'Storage type, derived from frontendInput (varchar, int, text, decimal, datetime, static)')]
    public ?string $backendType = null;

    #[ApiProperty(description: 'Whether a value is required')]
    public bool $isRequired = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute was created by a user (not a system attribute)')]
    public bool $isUserDefined = false;

    #[ApiProperty(description: 'Whether values must be unique across products')]
    public bool $isUnique = false;

    #[ApiProperty(description: 'Value scope: global, website or store', extraProperties: ['computed' => true], openapiContext: ['enum' => ['global', 'website', 'store']])]
    public string $scope = 'global';

    #[ApiProperty(description: 'Raw scope flag (0 = store view, 1 = global, 2 = website). scope is the readable form of the same value')]
    public int $isGlobal = \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL;

    #[ApiProperty(description: 'Default value. For a select or multiselect attribute, use isDefault on an option instead')]
    public ?string $defaultValue = null;

    #[ApiProperty(description: 'Whether the attribute is used in quick search')]
    public bool $isSearchable = false;

    #[ApiProperty(description: 'Layered navigation use (0 = no, 1 = filterable with results, 2 = filterable no results)')]
    public int $isFilterable = 0;

    #[ApiProperty(description: 'Whether the attribute is filterable in layered navigation on search results')]
    public bool $isFilterableInSearch = false;

    #[ApiProperty(description: 'Whether the attribute is comparable on the frontend')]
    public bool $isComparable = false;

    #[ApiProperty(description: 'Whether the attribute is visible on the frontend product page')]
    public bool $isVisibleOnFront = false;

    #[ApiProperty(description: 'Whether HTML is allowed in the frontend value')]
    public bool $isHtmlAllowedOnFront = false;

    #[ApiProperty(description: 'Whether the attribute can be used in catalog price rules')]
    public bool $isUsedForPriceRules = false;

    #[ApiProperty(description: 'Whether the attribute is loaded in product listings')]
    public bool $usedInProductListing = false;

    #[ApiProperty(description: 'Whether the attribute can be used to sort product listings')]
    public bool $usedForSortBy = false;

    #[ApiProperty(description: 'Whether the attribute is visible in advanced search')]
    public bool $isVisibleInAdvancedSearch = false;

    #[ApiProperty(description: 'Whether the WYSIWYG editor is enabled for the attribute')]
    public bool $isWysiwygEnabled = false;

    #[ApiProperty(description: 'Whether the attribute can be used to create configurable products')]
    public bool $isConfigurable = false;

    /**
     * @var string[]
     */
    #[ApiProperty(description: 'Product types the attribute applies to (simple, virtual, bundle, downloadable, configurable, grouped); empty = all', extraProperties: ['computed' => true])]
    public array $applyTo = [];

    #[ApiProperty(description: 'Frontend input validation class (for example validate-number, validate-email) or null')]
    public ?string $frontendClass = null;

    #[ApiProperty(description: 'Admin note shown under the input')]
    public ?string $note = null;

    #[ApiProperty(description: 'Position in layered navigation')]
    public int $position = 0;

    /**
     * Source options for select/multiselect attributes. Populated by the provider.
     *
     * @var array<array{label: string, value: string}>
     */
    #[ApiProperty(writable: false, description: 'Options of a select or multiselect attribute: [{label, value}], value is the option ID. Managed through /product-attributes/{id}/options', extraProperties: ['computed' => true])]
    public array $options = [];

    /**
     * Derive the human-readable scope from the catalog is_global flag and the
     * apply-to list from its comma-separated storage.
     */
    public static function afterLoad(self $dto, object $model): void
    {
        $dto->scope = match ((int) $model->getData('is_global')) {
            \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE => 'website',
            \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE => 'store',
            default => 'global',
        };

        $applyTo = $model->getData('apply_to');
        if (is_array($applyTo)) {
            $dto->applyTo = array_values($applyTo);
        } elseif (is_string($applyTo) && $applyTo !== '') {
            $dto->applyTo = explode(',', $applyTo);
        }
    }
}
