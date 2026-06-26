<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View'],
    shortName: 'BlogCategory',
    description: 'Blog category resource',
    provider: BlogCategoryProvider::class,
    operations: [
        new Get(
            uriTemplate: '/blog-categories/{id}',
            security: 'true',
            description: 'Get a blog category by ID',
        ),
        new GetCollection(
            uriTemplate: '/blog-categories',
            security: 'true',
            description: 'Get blog category collection',
        ),
    ],
    graphQlOperations: [
        new Query(
            security: 'true',
            name: 'item_query',
            description: 'Get a blog category by ID',
        ),
        new QueryCollection(
            security: 'true',
            name: 'collection_query',
            description: 'Get blog categories',
        ),
        new QueryCollection(
            security: 'true',
            name: 'blogCategories',
            args: ['urlKey' => ['type' => 'String']],
            description: 'Get blog categories, optionally filter by URL key',
        ),
    ],
)]
class BlogCategory extends CrudResource
{
    public const MODEL = 'blog/category';

    /** Admin ACL gate. Mirrors backend Maho_Blog_Adminhtml_Blog_CategoryController. */
    public const ADMIN_RESOURCE = \Maho_Blog_Adminhtml_Blog_CategoryController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $name = '';
    public string $urlKey = '';
    public ?int $parentId = null;
    public ?string $path = null;
    public int $level = 0;
    public int $position = 0;
    public bool $isActive = true;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
}
