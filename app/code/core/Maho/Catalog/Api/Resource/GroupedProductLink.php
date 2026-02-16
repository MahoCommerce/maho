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
use Maho\Catalog\Api\State\Processor\GroupedProductLinkProcessor;
use Maho\Catalog\Api\State\Provider\GroupedProductLinkProvider;

#[ApiResource(
    shortName: 'GroupedProductLink',
    description: 'Grouped product child associations',
    provider: GroupedProductLinkProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/grouped',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            description: 'List associated products in a grouped product',
        ),
        new Put(
            uriTemplate: '/products/{productId}/grouped',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: GroupedProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Replace all grouped associations',
        ),
        new Post(
            uriTemplate: '/products/{productId}/grouped',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: GroupedProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Add a grouped association',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/grouped/{childProductId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: GroupedProductLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove a grouped association',
        ),
    ],
)]
class GroupedProductLink
{
    #[ApiProperty(identifier: true, description: 'Composite identifier')]
    public ?string $id = null;

    #[ApiProperty(description: 'Child product ID')]
    public int $childProductId = 0;

    #[ApiProperty(description: 'Child product SKU')]
    public ?string $childProductSku = null;

    #[ApiProperty(description: 'Child product name')]
    public ?string $childProductName = null;

    #[ApiProperty(description: 'Default quantity')]
    public float $qty = 1;

    #[ApiProperty(description: 'Position/sort order')]
    public int $position = 0;
}
