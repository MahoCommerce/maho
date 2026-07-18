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

// Writes are gated by products/write|delete in ProductMediaProcessor (a product
// facet, not a separately-grantable resource), so this uses the plain API
// Platform attribute and is intentionally absent from the permission registry.
#[ApiResource(
    security: 'true',
    shortName: 'ProductMedia',
    description: 'Product media gallery images',
    provider: ProductMediaProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            security: 'true',
            description: 'List gallery images for a product',
        ),
        new Post(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductMediaProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Upload an image (JSON with base64 or URL)',
        ),
        new Put(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductMediaProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Update image label, position, types, or disabled status',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/media',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ProductMediaProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/delete')",
            description: 'Remove an image from the gallery',
        ),
    ],
)]
class ProductMedia extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

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
