<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Catalog
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
    shortName: 'DownloadableLink',
    description: 'Downloadable product links',
    provider: DownloadableLinkProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/downloadable-links',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            security: 'true',
            description: 'Get downloadable links for a product',
        ),
        new Post(
            uriTemplate: '/products/{productId}/downloadable-links',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: DownloadableLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Add a downloadable link',
        ),
        new Put(
            uriTemplate: '/products/{productId}/downloadable-links',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: DownloadableLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Update a downloadable link',
        ),
        new Delete(
            uriTemplate: '/products/{productId}/downloadable-links',
            uriVariables: [
                'productId' => new Link(fromClass: Product::class, identifiers: ['id']),
            ],
            processor: DownloadableLinkProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Remove a downloadable link',
        ),
    ],
)]
class DownloadableLink extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Gated under product management. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_Catalog_ProductController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Link ID')]
    public ?int $id = null;

    #[ApiProperty(description: 'Link title')]
    public string $title = '';

    #[ApiProperty(description: 'Link price')]
    public float $price = 0;

    #[ApiProperty(description: 'Sort order')]
    public int $sortOrder = 0;

    #[ApiProperty(description: 'Number of downloads (0 = unlimited)')]
    public int $numberOfDownloads = 0;

    #[ApiProperty(description: 'Link type (url or file)')]
    public string $linkType = 'url';

    #[ApiProperty(description: 'Link URL (when linkType = url)')]
    public ?string $linkUrl = null;

    #[ApiProperty(description: 'Sample URL')]
    public ?string $sampleUrl = null;

    #[ApiProperty(description: 'Sample type (url or file)')]
    public ?string $sampleType = null;
}
