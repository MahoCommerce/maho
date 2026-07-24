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
    shortName: 'ConfigurableSetup',
    description: 'Configurable product super attributes and child assignments',
    provider: ConfigurableSetupProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/configurable',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            security: 'true',
            description: 'Get super attributes and child product IDs',
        ),
        new Put(
            uriTemplate: '/products/{productId}/configurable',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ConfigurableSetupProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Set super attributes and assign all children',
        ),
        new Post(
            uriTemplate: '/products/{productId}/configurable/children',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ConfigurableSetupProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/write')",
            description: 'Add a child product',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/configurable/children/{childId}',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: ConfigurableSetupProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('products/delete')",
            description: 'Remove a child product',
        ),
    ],
)]
class ConfigurableSetup extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Product ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Super attribute codes')]
    public array $superAttributes = [];

    #[ApiProperty(description: 'Child product IDs')]
    public array $childProductIds = [];
}
