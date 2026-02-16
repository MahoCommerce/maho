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
use Maho\Catalog\Api\State\Processor\ProductMediaProcessor;
use Maho\Catalog\Api\State\Provider\ProductMediaProvider;

#[ApiResource(
    shortName: 'ProductMedia',
    description: 'Product media gallery images',
    provider: ProductMediaProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            description: 'List gallery images for a product',
        ),
        new Post(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductMediaProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Upload an image (JSON with base64 or URL)',
        ),
        new Put(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductMediaProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Update image label, position, types, or disabled status',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductMediaProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove an image from the gallery',
        ),
    ],
)]
class ProductMedia
{
    #[ApiProperty(identifier: true, description: 'Gallery value ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Relative file path')]
    public ?string $file = null;

    #[ApiProperty(description: 'Full image URL')]
    public ?string $url = null;

    #[ApiProperty(description: 'Image label/alt text')]
    public ?string $label = null;

    #[ApiProperty(description: 'Sort position')]
    public int $position = 0;

    #[ApiProperty(description: 'Whether image is hidden')]
    public bool $disabled = false;

    /** @var string[] */
    #[ApiProperty(description: 'Image roles (image, small_image, thumbnail)')]
    public array $types = [];
}
