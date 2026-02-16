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
use Maho\Catalog\Api\State\Processor\ConfigurableSetupProcessor;
use Maho\Catalog\Api\State\Provider\ConfigurableSetupProvider;

#[ApiResource(
    shortName: 'ConfigurableSetup',
    description: 'Configurable product super attributes and child assignments',
    provider: ConfigurableSetupProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/configurable',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            description: 'Get super attributes and child product IDs',
        ),
        new Put(
            uriTemplate: '/products/{productId}/configurable',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ConfigurableSetupProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Set super attributes and assign all children',
        ),
        new Post(
            uriTemplate: '/products/{productId}/configurable/children',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ConfigurableSetupProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Add a child product',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/configurable/children/{childId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ConfigurableSetupProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove a child product',
        ),
    ],
)]
class ConfigurableSetup
{
    #[ApiProperty(identifier: true, description: 'Product ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Super attribute codes')]
    public array $superAttributes = [];

    #[ApiProperty(description: 'Child product IDs')]
    public array $childProductIds = [];
}
