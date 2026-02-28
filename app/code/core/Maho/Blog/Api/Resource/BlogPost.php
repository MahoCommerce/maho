<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Blog\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Blog\Api\State\Processor\BlogPostProcessor;
use Maho\Blog\Api\State\Provider\BlogPostProvider;
use ApiPlatform\Metadata\ApiProperty;

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
        new Post(
            uriTemplate: '/blog-posts',
            processor: BlogPostProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Creates a new blog post',
        ),
        new Put(
            uriTemplate: '/blog-posts/{id}',
            processor: BlogPostProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Updates a blog post',
        ),
        new Delete(
            uriTemplate: '/blog-posts/{id}',
            processor: BlogPostProcessor::class,
            security: "is_granted('ROLE_API_USER')",
            description: 'Deletes a blog post',
        ),
    ],
    graphQlOperations: [
        new Query(name: 'item_query', description: 'Get a blog post by ID'),
        new QueryCollection(name: 'collection_query', description: 'Get blog posts'),
        new QueryCollection(
            name: 'blogPosts',
            args: ['urlKey' => ['type' => 'String']],
            description: 'Get blog posts, optionally filter by URL key',
        ),
    ],
)]
class BlogPost
{
    public ?int $id = null;
    public string $title = '';
    public string $urlKey = '';
    public ?string $content = null;
    public ?string $shortContent = null;
    public ?string $excerpt = null;
    public ?string $image = null;
    public ?string $imageUrl = null;
    public ?string $publishDate = null;
    public ?string $publishedAt = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;
    public string $status = 'enabled';
    public bool $isActive = true;
    /** @var string[] */
    public array $stores = ['all'];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Module-provided extension data.
     * Populated via api_{resource}_dto_build event. Modules can append
     * arbitrary keyed data here without modifying core API resources.
     * @var array<string, mixed>
     */
    #[ApiProperty(description: 'Module-provided extension data')]
    public array $extensions = [];

}
