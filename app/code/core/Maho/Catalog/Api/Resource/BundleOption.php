<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Catalog\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Catalog\Api\State\Processor\BundleOptionProcessor;
use Maho\Catalog\Api\State\Provider\BundleOptionProvider;

#[ApiResource(
    shortName: 'BundleOption',
    description: 'Bundle product options and selections',
    provider: BundleOptionProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/bundle-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            description: 'Get bundle options with selections',
        ),
        new Post(
            uriTemplate: '/products/{productId}/bundle-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: BundleOptionProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Add a bundle option with selections',
        ),
        new Put(
            uriTemplate: '/products/{productId}/bundle-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: BundleOptionProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Update a bundle option',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/bundle-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: BundleOptionProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove a bundle option',
        ),
    ],
)]
class BundleOption
{
    #[ApiProperty(identifier: true, description: 'Option ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Option title')]
    public string $title = '';

    #[ApiProperty(description: 'Input type (select, radio, checkbox, multi)')]
    public string $type = 'select';

    #[ApiProperty(description: 'Is option required')]
    public bool $required = true;

    #[ApiProperty(description: 'Sort order')]
    public int $position = 0;

    /** @var array<array{productId: int, sku: string, name: string, price: float, priceType: string, qty: float, canChangeQty: bool, isDefault: bool, position: int}> */
    #[ApiProperty(description: 'Bundle selections')]
    public array $selections = [];
}
