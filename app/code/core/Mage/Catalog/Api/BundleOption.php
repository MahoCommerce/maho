<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

#[ApiResource(
    security: 'true',
    shortName: 'BundleOption',
    description: 'Bundle product options and selections',
    provider: BundleOptionProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/bundle-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            security: 'true',
            description: 'Get bundle options with selections',
        ),
        new Post(
            uriTemplate: '/products/{productId}/bundle-options',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: BundleOptionProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Add a bundle option with selections',
        ),
        new Put(
            uriTemplate: '/products/{productId}/bundle-options/{id}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: BundleOptionProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Update a bundle option',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/bundle-options/{id}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: BundleOptionProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/delete')",
            description: 'Remove a bundle option',
        ),
    ],
)]
class BundleOption extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Bundle options are gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

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

    /** @var array<array{selectionId?: int, productId: int, sku: string, name: string, price: float, priceType: string, qty: float, canChangeQty: bool, isDefault: bool, position: int}> */
    #[ApiProperty(description: 'Bundle selections')]
    public array $selections = [];
}
