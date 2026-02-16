<?php

declare(strict_types=1);

namespace Maho\Catalog\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Catalog\Api\State\Processor\ProductCustomOptionProcessor;
use Maho\Catalog\Api\State\Provider\ProductCustomOptionProvider;

#[ApiResource(
    shortName: 'ProductCustomOption',
    description: 'Product custom options (personalization, add-ons)',
    provider: ProductCustomOptionProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/custom-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            description: 'Get all custom options for a product',
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
class ProductCustomOption
{
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
