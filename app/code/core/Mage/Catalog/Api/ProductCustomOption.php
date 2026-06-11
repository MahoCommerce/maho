<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

#[ApiResource(
    mahoId: 'product-options',
    mahoLabel: 'Custom Options',
    mahoOperations: ['read' => 'View', 'write' => 'Manage', 'delete' => 'Delete'],
    shortName: 'ProductCustomOption',
    description: 'Product custom options (personalization, add-ons)',
    provider: ProductCustomOptionProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/custom-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            security: 'true',
            description: 'Get all custom options for a product',
        ),
        new GetCollection(
            uriTemplate: '/products/{sku}/options',
            name: 'get_options_by_sku',
            security: 'true',
            description: 'Get custom options for a product by SKU (resolves configurable parents)',
        ),
        new Get(
            uriTemplate: '/custom-option-file/{optionId}/{key}',
            name: 'download_option_file',
            security: 'true',
            description: 'Download a custom option file by option ID and secret key',
        ),
        new Post(
            uriTemplate: '/products/{productId}/custom-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductCustomOptionProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Add a custom option to a product',
        ),
        new Put(
            uriTemplate: '/products/{productId}/custom-options/{optionId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
                'optionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            processor: ProductCustomOptionProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Update a custom option',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/custom-options/{optionId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
                'optionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            processor: ProductCustomOptionProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove a custom option',
        ),
    ],
)]
class ProductCustomOption extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Option ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Option title')]
    public string $title = '';

    #[ApiProperty(description: 'Option type (field, area, drop_down, radio, checkbox, multiple, file, date, date_time, time)')]
    public string $type = 'field';

    #[ApiProperty(description: 'Whether option is required')]
    public bool $required = false;

    #[ApiProperty(description: 'Sort order')]
    public int $sortOrder = 0;

    #[ApiProperty(description: 'Price (for non-select types)')]
    public ?float $price = null;

    #[ApiProperty(description: 'Price type: fixed or percent (for non-select types)')]
    public string $priceType = 'fixed';

    #[ApiProperty(description: 'Max characters (for field/area types)')]
    public ?int $maxCharacters = null;

    #[ApiProperty(description: 'Allowed file extensions (for file type)')]
    public ?string $fileExtensions = null;

    #[ApiProperty(description: 'SKU suffix (for non-select types)')]
    public ?string $sku = null;

    #[ApiProperty(description: 'Values for select-type options (drop_down, radio, checkbox, multiple)', writable: false)]
    public array $values = [];
}
