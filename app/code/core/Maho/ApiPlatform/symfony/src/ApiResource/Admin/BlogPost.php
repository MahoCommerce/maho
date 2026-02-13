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

namespace Maho\ApiPlatform\ApiResource\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\State\Admin\BlogPostProcessor;
use Maho\ApiPlatform\State\Admin\BlogPostProvider;

#[ApiResource(
    uriTemplate: '/admin/blog-posts',
    shortName: 'AdminBlogPost',
    operations: [
        new Post(
            processor: BlogPostProcessor::class,
            description: 'Creates a new blog post with content sanitization',
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
#[ApiResource(
    uriTemplate: '/admin/blog-posts/{id}',
    shortName: 'AdminBlogPost',
    provider: BlogPostProvider::class,
    operations: [
        new Put(
            processor: BlogPostProcessor::class,
            description: 'Updates an existing blog post with content sanitization',
        ),
        new Delete(
            processor: BlogPostProcessor::class,
            description: 'Deletes a blog post',
        ),
    ],
    security: "is_granted('ROLE_ADMIN_API')",
)]
class BlogPost
{
    public ?int $id = null;
    public string $identifier = '';
    public string $title = '';
    public ?string $shortContent = null;
    public string $content = '';
    public ?string $author = null;
    public bool $isActive = true;
    public ?string $publishedAt = null;
    public ?string $metaTitle = null;
    public ?string $metaKeywords = null;
    public ?string $metaDescription = null;
    /** @var string[] */
    public array $stores = ['all'];
    /** @var string|null Image path relative to media/blog/ or full URL to copy */
    public ?string $image = null;
}
