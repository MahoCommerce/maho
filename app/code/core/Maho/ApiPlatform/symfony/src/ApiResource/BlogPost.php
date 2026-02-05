<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\ApiPlatform\State\Provider\BlogPostProvider;

#[ApiResource(
    shortName: 'BlogPost',
    description: 'Blog post resource',
    provider: BlogPostProvider::class,
    operations: [
        new Get(
            uriTemplate: '/blog-posts/{id}',
            description: 'Get a blog post by ID',
        ),
        new GetCollection(
            uriTemplate: '/blog-posts',
            description: 'Get blog post collection',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'blogPost', description: 'Get a blog post by ID'),
        new QueryCollection(name: 'blogPosts', description: 'Get blog posts'),
        new Query(
            name: 'blogPostByUrlKey',
            args: ['urlKey' => ['type' => 'String!']],
            description: 'Get a blog post by URL key',
        ),
    ],
)]
class BlogPost
{
    public ?int $id = null;
    public string $title = '';
    public string $urlKey = '';
    public ?string $content = null;
    public ?string $excerpt = null;
    public ?string $imageUrl = null;
    public ?string $publishDate = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
    public string $status = 'enabled';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
