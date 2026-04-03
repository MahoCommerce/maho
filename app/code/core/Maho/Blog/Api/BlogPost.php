<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Service\StoreContext;

#[ApiResource(
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
class BlogPost extends CrudResource
{
    public const MODEL = 'blog/post';

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
    public ?string $publishedAt = null;
    public ?string $metaTitle = null;
    public ?string $metaDescription = null;
    public ?string $metaKeywords = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public string $status = 'enabled';

    public bool $isActive = true;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    /** @var string[] */
    public array $stores = ['all'];

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    /** @var int[] */
    public array $categoryIds = [];

    #[ApiProperty(writable: false, extraProperties: ['computed' => true])]
    public ?string $excerpt = null;

    #[ApiProperty(writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false)]
    public ?string $updatedAt = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->content = self::filterContent($dto->content ?? '');
        $dto->status = $dto->isActive ? 'enabled' : 'disabled';
        $dto->imageUrl = $model->getImageUrl();

        $postStores = $model->getStores();
        $dto->stores = StoreContext::storeIdsToStoreCodes($postStores);
        $dto->categoryIds = array_map('intval', $model->getCategories());

        if ($model->getContent()) {
            $text = strip_tags($model->getContent());
            $dto->excerpt = mb_strlen($text) > 200
                ? mb_substr($text, 0, 200) . '...'
                : $text;
        }
    }
}
