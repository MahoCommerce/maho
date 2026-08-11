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

    #[ApiProperty(writable: false, description: 'Raw scope flag (0 = store view, 1 = global, 2 = website)')]
    public int $isGlobal = \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL;

    #[ApiProperty(writable: false, description: 'Default value')]
    public ?string $defaultValue = null;

    #[ApiProperty(writable: false, description: 'Whether the attribute is used in quick search')]
    public bool $isSearchable = false;

    #[ApiProperty(writable: false, description: 'Layered navigation use (0 = no, 1 = filterable with results, 2 = filterable no results)')]
    public int $isFilterable = 0;

    #[ApiProperty(writable: false, description: 'Whether the attribute is filterable in layered navigation on search results')]
    public bool $isFilterableInSearch = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute is comparable on the frontend')]
    public bool $isComparable = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute is visible on the frontend product page')]
    public bool $isVisibleOnFront = false;

    #[ApiProperty(writable: false, description: 'Whether HTML is allowed in the frontend value')]
    public bool $isHtmlAllowedOnFront = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute can be used in catalog price rules')]
    public bool $isUsedForPriceRules = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute is loaded in product listings')]
    public bool $usedInProductListing = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute can be used to sort product listings')]
    public bool $usedForSortBy = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute is visible in advanced search')]
    public bool $isVisibleInAdvancedSearch = false;

    #[ApiProperty(writable: false, description: 'Whether the WYSIWYG editor is enabled for the attribute')]
    public bool $isWysiwygEnabled = false;

    #[ApiProperty(writable: false, description: 'Whether the attribute can be used to create configurable products')]
    public bool $isConfigurable = false;

    /**
     * @var string[]
     */
    #[ApiProperty(writable: false, description: 'Product types the attribute applies to (empty = all)', extraProperties: ['computed' => true])]
    public array $applyTo = [];

    #[ApiProperty(writable: false, description: 'Frontend input validation class')]
    public ?string $frontendClass = null;

    #[ApiProperty(writable: false, description: 'Admin note shown under the input')]
    public ?string $note = null;

    #[ApiProperty(writable: false, description: 'Position in layered navigation')]
    public int $position = 0;

    /**
     * Source options for select/multiselect attributes. Populated by the provider.
     *
     * @var array<array{label: string, value: string}>
     */
    #[ApiProperty(writable: false, description: 'Source options for select/multiselect attributes', extraProperties: ['computed' => true])]
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
