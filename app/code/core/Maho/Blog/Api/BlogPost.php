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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;

#[ApiResource(
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'BlogPost',
    description: 'Blog post resource',
    provider: BlogPostProvider::class,
    operations: [
        new Get(
            uriTemplate: '/blog-posts/{id}',
            security: 'true',
            description: 'Get a blog post by ID',
        ),
        new GetCollection(
            uriTemplate: '/blog-posts',
            security: 'true',
            description: 'Get blog post collection',
        ),
        new Post(
            uriTemplate: '/blog-posts',
            processor: CrudProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-posts/write')",
            description: 'Creates a new blog post',
        ),
        new Put(
            uriTemplate: '/blog-posts/{id}',
            processor: CrudProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-posts/write')",
            description: 'Updates a blog post',
        ),
        new Delete(
            uriTemplate: '/blog-posts/{id}',
            processor: CrudProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('blog-posts/delete')",
            description: 'Deletes a blog post',
        ),
    ],
    graphQlOperations: [
        new Query(
            security: 'true',
            name: 'item_query',
            description: 'Get a blog post by ID',
        ),
        new QueryCollection(
            security: 'true',
            name: 'collection_query',
            description: 'Get blog posts',
        ),
        new QueryCollection(
            security: 'true',
            name: 'blogPosts',
            args: ['urlKey' => ['type' => 'String']],
            description: 'Get blog posts, optionally filter by URL key',
        ),
    ],
)]
class BlogPost extends CrudResource
{
    public const MODEL = 'blog/post';

    /** Admin ACL gate. Mirrors backend Maho_Blog_Adminhtml_Blog_PostController. */
    public const ADMIN_RESOURCE = \Maho_Blog_Adminhtml_Blog_PostController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    public string $title = '';
    public string $urlKey = '';
    public ?string $content = null;
    public ?string $shortContent = null;
    public ?string $image = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $imageUrl = null;

    public ?string $publishDate = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    public ?bool $isActive = null;

    /** @var int[]|null */
    public ?array $stores = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    /** @var int[] */
    public array $categoryIds = [];

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $excerpt = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    #[\Override]
    public function applyToModel(object $model): void
    {
        parent::applyToModel($model);

        // On create, apply sensible defaults for fields omitted from the request.
        // On partial update these stay untouched (parent::applyToModel skips nulls),
        // so an enabled/store-restricted post is not silently reset.
        if (!$model->getId()) {
            if ($this->isActive === null) {
                $model->setData('is_active', 1);
            }
            if ($this->stores === null) {
                $model->setData('stores', [0]);
            }
        }
    }

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->content = self::filterContent($dto->content ?? '');
        $dto->status = $dto->isActive ? 'enabled' : 'disabled';
        $dto->imageUrl = $model->getImageUrl();

        $dto->stores = array_map('intval', $model->getStores());
        $dto->categoryIds = array_map('intval', $model->getCategories());

        if ($model->getContent()) {
            $text = strip_tags($model->getContent());
            $dto->excerpt = mb_strlen($text) > 200
                ? mb_substr($text, 0, 200) . '...'
                : $text;
        }
    }
}
